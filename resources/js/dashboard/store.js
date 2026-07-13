import { request } from './api';
import { tempId } from './ids';
import * as storage from './storage';
import { enqueue, hasPendingFor, pendingDeleteIds } from './sync';

const ENTITIES = ['projects', 'properties', 'articles', 'visitors'];

export const dataStore = {
    projects: storage.load('entities.projects', []),
    properties: storage.load('entities.properties', []),
    articles: storage.load('entities.articles', []),
    visitors: storage.load('entities.visitors', []),
    stats: storage.load('entities.stats', null),
    loadedAt: storage.load('meta.loadedAt', {}),

    persist(entity) {
        storage.save('entities.' + entity, this[entity]);
    },

    find(entity, id) {
        return this[entity].find((record) => record.id === id) ?? null;
    },

    setSyncState(entity, id, state) {
        const record = this.find(entity, id);

        if (record) {
            record._meta = { ...(record._meta ?? {}), syncState: state };
            this.persist(entity);
        }
    },

    removeLocal(entity, id) {
        this[entity] = this[entity].filter((record) => record.id !== id);
        this.persist(entity);
    },

    /**
     * Optimistically create a record and queue its sync.
     */
    createRecord(entity, attributes) {
        const id = tempId();
        const record = { ...attributes, id, _meta: { syncState: 'queued' } };

        this[entity] = [record, ...this[entity]];
        this.persist(entity);
        enqueue({ entity, action: 'create', targetId: id, payload: attributes });

        return record;
    },

    /**
     * Optimistically update a record and queue its sync.
     */
    updateRecord(entity, id, attributes) {
        const record = this.find(entity, id);

        if (!record) {
            return null;
        }

        Object.assign(record, attributes);
        record._meta = { ...(record._meta ?? {}), syncState: 'queued' };
        this.persist(entity);
        enqueue({ entity, action: 'update', targetId: id, payload: attributes });

        return record;
    },

    /**
     * Optimistically delete a record and queue its sync.
     */
    deleteRecord(entity, id) {
        this.removeLocal(entity, id);
        enqueue({ entity, action: 'delete', targetId: id });
    },

    confirmCreate(entity, temporaryId, serverRecord) {
        const index = this[entity].findIndex((record) => record.id === temporaryId);
        const confirmed = { ...serverRecord, _meta: { syncState: 'synced' } };

        if (index === -1) {
            this[entity] = [confirmed, ...this[entity]];
        } else {
            this[entity].splice(index, 1, confirmed);
        }

        this.persist(entity);
    },

    confirmUpdate(entity, serverRecord) {
        if (hasPendingFor(entity, serverRecord.id)) {
            return;
        }

        const index = this[entity].findIndex((record) => record.id === serverRecord.id);

        if (index !== -1) {
            this[entity].splice(index, 1, { ...serverRecord, _meta: { syncState: 'synced' } });
            this.persist(entity);
        }
    },

    /**
     * Merge a file-upload response (fresh server copy) into the local cache.
     */
    applyServerRecord(entity, serverRecord) {
        const index = this[entity].findIndex((record) => record.id === serverRecord.id);
        const local = index === -1 ? null : this[entity][index];
        const syncState = local?._meta?.syncState === 'synced' || !local ? 'synced' : local._meta.syncState;
        const merged = { ...(local ?? {}), ...serverRecord, _meta: { syncState } };

        if (index === -1) {
            this[entity] = [merged, ...this[entity]];
        } else {
            this[entity].splice(index, 1, merged);
        }

        this.persist(entity);
    },

    /**
     * Stale-while-revalidate: replace synced records with server truth while
     * keeping local records that still have queued or failed operations.
     */
    async revalidate(entity) {
        if (!ENTITIES.includes(entity)) {
            return;
        }

        const payload = await request('GET', '/api/' + entity);
        const deletedIds = pendingDeleteIds(entity);

        const localPending = this[entity].filter(
            (record) => record._meta && record._meta.syncState !== 'synced' && hasPendingFor(entity, record.id)
        );
        const pendingIds = new Set(localPending.map((record) => record.id));

        const serverRecords = (payload?.data ?? [])
            .filter((record) => !deletedIds.has(record.id) && !pendingIds.has(record.id))
            .map((record) => ({ ...record, _meta: { syncState: 'synced' } }));

        this[entity] = [...localPending, ...serverRecords];
        this.loadedAt[entity] = Date.now();
        storage.save('meta.loadedAt', this.loadedAt);
        this.persist(entity);
    },

    async revalidateStats() {
        const payload = await request('GET', '/api/dashboard/stats');

        this.stats = payload?.data ?? null;
        storage.save('entities.stats', this.stats);
    },
};
