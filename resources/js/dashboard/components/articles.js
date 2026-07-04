import { isTemp } from '../ids';

export default function articlesPage() {
    return {
        panel: false,
        editingId: null,
        form: { title: '', content: '' },
        errors: {},
        search: '',

        init() {
            this.refresh();
        },

        async refresh() {
            if (!navigator.onLine) {
                return;
            }

            try {
                await this.$store.data.revalidate('articles');
            } catch {
                // keep cached data
            }
        },

        get articles() {
            const term = this.search.trim();
            const articles = this.$store.data.articles;

            return term ? articles.filter((article) => (article.title ?? '').includes(term)) : articles;
        },

        isTemp,

        canUploadFor(record) {
            return !isTemp(record.id) && this.$store.sync.online;
        },

        openCreate() {
            this.panel = true;
            this.editingId = null;
            this.errors = {};
            this.form = { title: '', content: '' };
        },

        openEdit(record) {
            this.panel = true;
            this.editingId = record.id;
            this.errors = {};
            this.form = { title: record.title ?? '', content: record.content ?? '' };
        },

        save() {
            this.errors = {};

            if (!(this.form.title ?? '').trim()) {
                this.errors.title = 'عنوان المقال مطلوب.';
            }

            if (Object.keys(this.errors).length) {
                return;
            }

            const attributes = { title: this.form.title, content: this.form.content || null };

            if (this.editingId) {
                this.$store.data.updateRecord('articles', this.editingId, attributes);
            } else {
                this.$store.data.createRecord('articles', attributes);
            }

            this.closePanel();
        },

        remove(record) {
            if (confirm(`هل أنت متأكد من حذف المقال «${record.title}»؟`)) {
                this.$store.data.deleteRecord('articles', record.id);
            }
        },

        closePanel() {
            this.panel = false;
            this.editingId = null;
            this.errors = {};
        },

        get editingRecord() {
            return this.editingId ? this.$store.data.find('articles', this.editingId) : null;
        },

        insertTag(startTag, endTag) {
            const textarea = this.$refs.contentArea;

            if (!textarea) {
                return;
            }

            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = this.form.content ?? '';
            const selected = value.substring(start, end);

            this.form.content = value.substring(0, start) + startTag + selected + endTag + value.substring(end);

            this.$nextTick(() => {
                textarea.focus();
                const cursor = start + startTag.length + selected.length;
                textarea.setSelectionRange(cursor, cursor);
            });
        },

        async uploadCover(event, record) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            try {
                const payload = await this.$store.uploads.start({
                    url: `/api/articles/${record.id}/image`,
                    formData,
                    label: `غلاف «${record.title}»`,
                });

                this.$store.data.applyServerRecord('articles', payload.data);
            } catch (error) {
                alert(error.message);
            }

            event.target.value = '';
        },
    };
}
