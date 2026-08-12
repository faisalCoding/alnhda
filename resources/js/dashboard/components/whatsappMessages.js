import { request } from '../api';

export const DELIVERY_STATUSES = {
    queued: { label: 'في الطابور', tone: 'amber' },
    sent: { label: 'أُرسلت', tone: 'sky' },
    delivered: { label: 'تم الاستلام', tone: 'emerald' },
    read: { label: 'تمت القراءة', tone: 'emerald' },
    failed: { label: 'فشلت', tone: 'red' },
};

export default function whatsappMessagesPage() {
    return {
        messages: [],
        loading: false,
        error: '',
        expanded: null,
        search: '',
        statusFilter: '',

        init() {
            this.refresh();
        },

        async refresh() {
            this.loading = true;
            this.error = '';

            try {
                const payload = await request('GET', '/api/whatsapp/messages');

                this.messages = payload?.data ?? [];
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },

        toggle(id) {
            this.expanded = this.expanded === id ? null : id;
        },

        statusLabel(status) {
            return DELIVERY_STATUSES[status]?.label ?? status;
        },

        statusClass(status) {
            return {
                amber: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
                sky: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
                emerald: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
                red: 'bg-red-500/15 text-red-600 dark:text-red-400',
            }[DELIVERY_STATUSES[status]?.tone] ?? 'bg-zinc-500/15 text-zinc-600';
        },

        /** Recipients of the open message, after the search and status filters. */
        recipientsOf(message) {
            const term = this.search.trim();

            return message.recipients.filter((recipient) => {
                if (this.statusFilter && recipient.status !== this.statusFilter) {
                    return false;
                }

                return !term || `${recipient.name} ${recipient.phone}`.includes(term);
            });
        },

        preview(body) {
            return body.length > 90 ? body.slice(0, 90) + '…' : body;
        },

        formatDate(value) {
            if (!value) {
                return '—';
            }

            return new Date(value).toLocaleString('ar-SA', {
                dateStyle: 'medium',
                timeStyle: 'short',
            });
        },
    };
}
