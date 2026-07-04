import Alpine from 'alpinejs';
import { ApiError, request, setCsrfToken } from './api';
import { isTemp, uuid } from './ids';
import * as storage from './storage';

const BACKOFF_DELAYS = [2000, 5000, 15000, 60000, 120000];

let draining = false;
let retryTimer = null;

export const syncStore = {
    online: navigator.onLine,
    syncing: false,
    authExpired: false,
    lastSyncedAt: storage.load('meta.lastSyncedAt', null),
    ops: storage.load('outbox', []),

    get pendingCount() {
        return this.ops.filter((op) => op.status !== 'failed').length;
    },

    get failedCount() {
        return this.ops.filter((op) => op.status === 'failed').length;
    },

    get hasWork() {
        return this.ops.length > 0;
    },

    async resume() {
        return resumeAfterLogin();
    },
};

const sync = () => Alpine.store('sync');
const data = () => Alpine.store('data');

function persistOps() {
    storage.save('outbox', sync().ops);
}

export function pendingOpsFor(entity) {
    return sync().ops.filter((op) => op.entity === entity);
}

export function hasPendingFor(entity, targetId) {
    return sync().ops.some((op) => op.entity === entity && op.targetId === targetId);
}

export function pendingDeleteIds(entity) {
    return new Set(
        sync()
            .ops.filter((op) => op.entity === entity && op.action === 'delete')
            .map((op) => op.targetId)
    );
}

/**
 * Queue a mutation. Returns the op, or null when the mutation cancelled out
 * (deleting a record whose creation never reached the server).
 */
export function enqueue({ entity, action, targetId, payload = null }) {
    const store = sync();

    if (action === 'update') {
        const existing = store.ops.find(
            (op) =>
                op.entity === entity &&
                op.targetId === targetId &&
                op.status !== 'inflight' &&
                (op.action === 'update' || op.action === 'create')
        );

        if (existing) {
            existing.payload = { ...existing.payload, ...payload };
            existing.status = 'pending';
            existing.attempts = 0;
            existing.lastError = null;
            existing.updatedAt = Date.now();
            persistOps();
            triggerDrain();

            return existing;
        }
    }

    if (action === 'delete') {
        store.ops = store.ops.filter(
            (op) =>
                !(op.entity === entity && op.targetId === targetId && op.status !== 'inflight' && op.action === 'update')
        );

        const queuedCreate = store.ops.find(
            (op) => op.entity === entity && op.targetId === targetId && op.action === 'create' && op.status !== 'inflight'
        );

        if (queuedCreate) {
            store.ops = store.ops.filter((op) => op !== queuedCreate);
            persistOps();

            return null;
        }
    }

    const op = {
        id: uuid(),
        entity,
        action,
        targetId,
        payload,
        status: 'pending',
        attempts: 0,
        lastError: null,
        createdAt: Date.now(),
        updatedAt: Date.now(),
    };

    store.ops.push(op);
    persistOps();
    triggerDrain();

    return op;
}

export function retryOp(opId) {
    const op = sync().ops.find((candidate) => candidate.id === opId);

    if (op && op.status === 'failed') {
        op.status = 'pending';
        op.attempts = 0;
        op.lastError = null;
        persistOps();
        triggerDrain();
    }
}

export function discardOp(opId) {
    const store = sync();
    const op = store.ops.find((candidate) => candidate.id === opId);

    if (!op || op.status === 'inflight') {
        return;
    }

    store.ops = store.ops.filter((candidate) => candidate.id !== opId);
    persistOps();

    if (op.action === 'create') {
        data().removeLocal(op.entity, op.targetId);
    } else if (navigator.onLine) {
        data()
            .revalidate(op.entity)
            .catch(() => {});
    } else {
        data().setSyncState(op.entity, op.targetId, 'synced');
    }
}

function referencedTempIds(op) {
    const ids = [];

    if (op.action !== 'create' && isTemp(op.targetId)) {
        ids.push(op.targetId);
    }

    if (op.payload && isTemp(op.payload.project_id)) {
        ids.push(op.payload.project_id);
    }

    return ids;
}

function isBlocked(op) {
    return referencedTempIds(op).some((tmp) =>
        sync().ops.some((other) => other.id !== op.id && other.action === 'create' && other.targetId === tmp)
    );
}

function nextPending() {
    return sync().ops.find((op) => op.status === 'pending' && !isBlocked(op)) ?? null;
}

function completeOp(op) {
    const store = sync();

    store.ops = store.ops.filter((candidate) => candidate.id !== op.id);
    store.lastSyncedAt = Date.now();
    storage.save('meta.lastSyncedAt', store.lastSyncedAt);
    persistOps();
}

function remapTempId(tempId, realId) {
    for (const op of sync().ops) {
        if (op.targetId === tempId) {
            op.targetId = realId;
        }

        if (op.payload && op.payload.project_id === tempId) {
            op.payload.project_id = realId;
        }
    }

    persistOps();
}

async function execute(op) {
    const base = '/api/' + op.entity;

    if (op.action === 'create') {
        return request('POST', base, op.payload, { idempotencyKey: op.id });
    }

    if (op.action === 'update') {
        return request('PUT', `${base}/${op.targetId}`, op.payload, { idempotencyKey: op.id });
    }

    return request('DELETE', `${base}/${op.targetId}`, null, { idempotencyKey: op.id });
}

function handleSuccess(op, payload) {
    if (op.action === 'create') {
        const record = payload?.data;

        if (record) {
            data().confirmCreate(op.entity, op.targetId, record);
            remapTempId(op.targetId, record.id);
        }
    } else if (op.action === 'update' && payload?.data) {
        data().confirmUpdate(op.entity, payload.data);
    }

    completeOp(op);
}

/**
 * Returns true when the drain loop must stop (retry scheduled or auth expired).
 */
async function handleFailure(op, error) {
    const status = error instanceof ApiError ? error.status : 0;

    if (status === 401 || status === 419) {
        op.status = 'pending';
        persistOps();
        data().setSyncState(op.entity, op.targetId, 'queued');

        if (status === 419) {
            try {
                const session = await request('GET', '/api/session');
                setCsrfToken(session.csrf);

                if (session.authenticated) {
                    scheduleRetry(200);

                    return true;
                }
            } catch {
                // fall through to auth expiry
            }
        }

        sync().authExpired = true;

        return true;
    }

    if (status === 404 && op.action === 'delete') {
        completeOp(op);

        return false;
    }

    if (status === 422 || status === 404 || status === 403) {
        op.status = 'failed';
        op.lastError = { status, message: error.message, errors: error.errors ?? {} };
        persistOps();
        data().setSyncState(op.entity, op.targetId, 'failed');

        return false;
    }

    op.attempts += 1;
    op.lastError = { status, message: error.message, errors: {} };

    if (op.attempts > BACKOFF_DELAYS.length) {
        op.status = 'failed';
        persistOps();
        data().setSyncState(op.entity, op.targetId, 'failed');

        return false;
    }

    op.status = 'pending';
    persistOps();
    data().setSyncState(op.entity, op.targetId, 'queued');
    scheduleRetry(BACKOFF_DELAYS[op.attempts - 1]);

    return true;
}

function scheduleRetry(delay) {
    clearTimeout(retryTimer);
    retryTimer = setTimeout(() => drain(), delay);
}

export async function drain() {
    const store = sync();

    if (draining || !navigator.onLine || store.authExpired) {
        return;
    }

    draining = true;
    store.syncing = true;

    const touchedEntities = new Set();

    try {
        let op;

        while ((op = nextPending())) {
            op.status = 'inflight';
            persistOps();
            data().setSyncState(op.entity, op.targetId, 'syncing');

            try {
                const payload = await execute(op);

                handleSuccess(op, payload);
                touchedEntities.add(op.entity);
            } catch (error) {
                const shouldStop = await handleFailure(op, error);

                if (shouldStop || sync().authExpired) {
                    break;
                }
            }
        }
    } finally {
        draining = false;
        store.syncing = false;
    }

    for (const entity of touchedEntities) {
        data()
            .revalidate(entity)
            .catch(() => {});
    }
}

export function triggerDrain() {
    queueMicrotask(() => drain());
}

export async function resumeAfterLogin() {
    try {
        const session = await request('GET', '/api/session');

        setCsrfToken(session.csrf);

        if (session.authenticated) {
            sync().authExpired = false;
            triggerDrain();

            return true;
        }
    } catch {
        // stay expired
    }

    return false;
}

export function startSync() {
    window.addEventListener('online', () => {
        sync().online = true;
        triggerDrain();
    });

    window.addEventListener('offline', () => {
        sync().online = false;
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            triggerDrain();
        }
    });

    setInterval(() => drain(), 30000);
    triggerDrain();
}
