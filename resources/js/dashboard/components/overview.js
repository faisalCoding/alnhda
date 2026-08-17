import { ACTION_LABELS, ENTITY_LABELS, OP_STATUS_LABELS } from '../labels';
import { discardOp, retryOp } from '../sync';

export default function overviewPage() {
    return {
        loading: false,

        init() {
            this.refresh();
        },

        async refresh() {
            if (!navigator.onLine) {
                return;
            }

            this.loading = !this.$store.data.stats;

            try {
                await this.$store.data.revalidateStats();
            } catch {
                // keep cached stats
            } finally {
                this.loading = false;
            }
        },

        get counts() {
            return (
                this.$store.data.stats?.counts ?? {
                    projects: this.$store.data.projects.length,
                    properties: this.$store.data.properties.length,
                    articles: this.$store.data.articles.length,
                    visitors: this.$store.data.visitors.length,
                    leads: this.$store.data.leads.length,
                }
            );
        },

        get latest() {
            return this.$store.data.stats?.latest ?? { projects: [], properties: [], articles: [], visitors: [], leads: [] };
        },

        get ops() {
            return this.$store.sync.ops;
        },

        opEntity(op) {
            return ENTITY_LABELS[op.entity] ?? op.entity;
        },

        opAction(op) {
            return ACTION_LABELS[op.action] ?? op.action;
        },

        opStatus(op) {
            return OP_STATUS_LABELS[op.status] ?? op.status;
        },

        opName(op) {
            return op.payload?.name ?? op.payload?.title ?? `#${op.targetId}`;
        },

        opError(op) {
            if (!op.lastError) {
                return null;
            }

            const messages = Object.values(op.lastError.errors ?? {}).flat();

            return messages[0] ?? op.lastError.message;
        },

        /**
         * رفض الخادم للحمولة (422) حكم ثابت لا عابر: إعادة إرسال الحمولة نفسها
         * ترجع بالخطأ نفسه إلى الأبد. المخرج الوحيد تعديل السجل — وعندها تُدمج
         * القيم المصححة في الإجراء العالق ويُعاد إرساله تلقائيًا.
         */
        needsEdit(op) {
            return op.status === 'failed' && op.lastError?.status === 422;
        },

        retry(op) {
            retryOp(op.id);
        },

        discard(op) {
            if (confirm('سيتم تجاهل هذا الإجراء نهائيًا ولن يصل إلى الخادم. هل أنت متأكد؟')) {
                discardOp(op.id);
            }
        },

        formatTime(timestamp) {
            return new Date(timestamp).toLocaleString('ar-SA', {
                hour: '2-digit',
                minute: '2-digit',
                day: 'numeric',
                month: 'short',
            });
        },
    };
}
