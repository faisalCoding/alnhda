import { VISITOR_FORM_LABELS } from '../labels';

export default function visitorsPage() {
    return {
        loading: false,
        search: '',

        init() {
            this.refresh();
        },

        async refresh() {
            if (!navigator.onLine) {
                return;
            }

            this.loading = !this.$store.data.visitors.length;

            try {
                await this.$store.data.revalidate('visitors');
            } catch {
                // keep cached data
            } finally {
                this.loading = false;
            }
        },

        get groups() {
            const term = this.search.trim();
            const groups = new Map();

            for (const visitor of this.$store.data.visitors) {
                const haystack = `${visitor.first_name ?? ''} ${visitor.last_name ?? ''} ${visitor.phone ?? ''} ${visitor.email ?? ''}`;

                if (term && !haystack.includes(term)) {
                    continue;
                }

                const key = visitor.form_name ?? 'unknown';

                if (!groups.has(key)) {
                    groups.set(key, []);
                }

                groups.get(key).push(visitor);
            }

            return Array.from(groups.entries()).map(([key, items]) => ({
                key,
                label: VISITOR_FORM_LABELS[key] ?? key,
                items,
            }));
        },

        formatDate(iso) {
            if (!iso) {
                return '—';
            }

            return new Date(iso).toLocaleString('ar-SA', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
    };
}
