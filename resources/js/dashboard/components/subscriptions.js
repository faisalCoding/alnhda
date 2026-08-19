import { request } from '../api';
import { crudPage } from './simpleCrud';

export default function subscriptionsPage() {
    return {
        ...crudPage('/api/subscriptions', { id: null, name: '', identifier: '', expires_on: '', payment_account: '', note: '' }),

        pinIsSet: false,
        pinPrompt: { open: false, recordId: null, value: '', error: '', busy: false },
        revealed: {},
        revealTimers: {},

        async init() {
            await Promise.all([this.load(), this.loadPinState()]);
            this.loading = false;
        },

        async loadPinState() {
            try {
                const payload = await request('GET', '/api/reveal-pin');
                this.pinIsSet = payload?.data?.is_set ?? false;
            } catch {
                this.pinIsSet = false;
            }
        },

        askForPin(record) {
            this.pinPrompt = { open: true, recordId: record.id, value: '', error: '', busy: false };
        },

        async submitPin() {
            this.pinPrompt.busy = true;
            this.pinPrompt.error = '';

            try {
                const payload = await request('POST', `/api/subscriptions/${this.pinPrompt.recordId}/reveal`, {
                    pin: this.pinPrompt.value,
                });

                this.revealed[this.pinPrompt.recordId] = payload.data.secret;
                clearTimeout(this.revealTimers[this.pinPrompt.recordId]);
                this.revealTimers[this.pinPrompt.recordId] = setTimeout(() => this.hide(this.pinPrompt.recordId), 60000);
                this.pinPrompt.open = false;
            } catch (error) {
                this.pinPrompt.error = error.errors?.pin?.[0] ?? error.message;
            } finally {
                this.pinPrompt.busy = false;
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
            return this.records.filter((record) => this.matches(record, record.name, record.identifier, record.note));
        },
    };
}
