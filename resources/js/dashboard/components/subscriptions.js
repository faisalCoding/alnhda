import { request } from '../api';
import { crudPage } from './simpleCrud';

export default function subscriptionsPage() {
    return {
        ...crudPage('/api/subscriptions', { id: null, account_id: '', name: '', amount: '', paid_on: '', expires_on: '', payment_account: '', note: '' }),

        accounts: [],
        kind: 'all',

        pinIsSet: false,
        unlockedUntil: null,
        pinPrompt: { open: false, recordId: null, value: '', error: '', busy: false },
        revealed: {},
        revealTimers: {},

        async init() {
            await Promise.all([this.load(), this.loadAccounts(), this.loadPinState()]);
            this.loading = false;
        },

        async loadAccounts() {
            try {
                const payload = await request('GET', '/api/accounts');
                this.accounts = payload?.data ?? [];
            } catch {
                this.accounts = [];
            }
        },

        async loadPinState() {
            try {
                const payload = await request('GET', '/api/reveal-pin');
                this.pinIsSet = payload?.data?.is_set ?? false;
                this.unlockedUntil = payload?.data?.unlocked_until ?? null;
            } catch {
                this.pinIsSet = false;
                this.unlockedUntil = null;
            }
        },

        /** True while the hour opened by the last correct pin is still running. */
        get isUnlocked() {
            return this.unlockedUntil !== null && new Date(this.unlockedUntil) > new Date();
        },

        async askForPin(record) {
            if (this.pinIsSet && this.isUnlocked) {
                await this.reveal(record.id);

                return;
            }

            this.pinPrompt = { open: true, recordId: record.id, value: '', error: '', busy: false };
        },

        async reveal(recordId, pin = null) {
            const payload = await request('POST', `/api/subscriptions/${recordId}/reveal`, pin === null ? {} : { pin });

            this.revealed[recordId] = payload.data.secret;
            this.unlockedUntil = payload.data.unlocked_until ?? this.unlockedUntil;
            clearTimeout(this.revealTimers[recordId]);
            this.revealTimers[recordId] = setTimeout(() => this.hide(recordId), 60000);
        },

        async submitPin() {
            this.pinPrompt.busy = true;
            this.pinPrompt.error = '';

            try {
                await this.reveal(this.pinPrompt.recordId, this.pinPrompt.value);
                this.pinPrompt.open = false;
            } catch (error) {
                this.pinPrompt.error = error.errors?.pin?.[0] ?? error.message;
            } finally {
                this.pinPrompt.busy = false;
            }
        },

        copied: null,

        /** Copy the identifier and flag it, so the button can confirm itself. */
        /** The linked account owns the identifier, so an unlinked record has none. */
        async copyIdentifier(record) {
            if (!record.identifier) {
                return;
            }

            try {
                await navigator.clipboard.writeText(record.identifier);
                this.copied = 'id-' + record.id;
                setTimeout(() => {
                    if (this.copied === 'id-' + record.id) {
                        this.copied = null;
                    }
                }, 1500);
            } catch {
                this.error = 'تعذر النسخ إلى الحافظة';
            }
        },

        hide(id) {
            clearTimeout(this.revealTimers[id]);
            delete this.revealTimers[id];
            delete this.revealed[id];
        },

        /** Colour the row by how close renewal is. */
        expiryTone(record) {
            const days = record.days_until_expiry;

            if (days === null) {
                return 'text-zinc-400';
            }

            if (days < 0) {
                return 'text-red-600 dark:text-red-400 font-bold';
            }

            if (days <= 14) {
                return 'text-amber-600 dark:text-amber-400 font-bold';
            }

            return 'text-zinc-600 dark:text-zinc-300';
        },

        expiryLabel(record) {
            const days = record.days_until_expiry;

            if (days === null) {
                return 'بلا تاريخ';
            }

            if (days < 0) {
                return `انتهى منذ ${Math.abs(days)} يوم`;
            }

            if (days === 0) {
                return 'ينتهي اليوم';
            }

            return `${days} يوم متبق`;
        },

        get visible() {
            return this.records.filter((record) => {
                const ofKind = this.kind === 'all'
                    || (this.kind === 'subscription' && record.is_subscription)
                    || (this.kind === 'payment' && !record.is_subscription);

                return ofKind && this.matches(record, record.display_name, record.identifier, record.note);
            });
        },

        countOf(kind) {
            if (kind === 'all') {
                return this.records.length;
            }

            return this.records.filter((record) => (kind === 'subscription') === Boolean(record.is_subscription)).length;
        },

        /** What the records on screen add up to. */
        get totalAmount() {
            return this.visible.reduce((sum, record) => sum + Number(record.amount ?? 0), 0);
        },

        formatAmount(value) {
            if (value === null || value === undefined || value === '') {
                return null;
            }

            return new Intl.NumberFormat('ar-SA-u-nu-latn', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(value));
        },
    };
}
