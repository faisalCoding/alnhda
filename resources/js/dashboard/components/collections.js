import { isTemp } from '../ids';
import { ITEM_KINDS, labelForValue, linkOptions, moveItem } from '../links';
import { isValidSlug, toSlug } from '../validation';

const KIND_LABELS = { project: 'مشروع', properties: 'وحدة', article: 'مقال' };

const blankForm = () => ({ title: '', slug: '', description: '', items: [] });

export default function collectionsPage() {
    return {
        panel: false,
        editingId: null,
        form: blankForm(),
        errors: {},
        search: '',
        picked: '',

        init() {
            this.refresh();
        },

        async refresh() {
            if (!navigator.onLine) {
                return;
            }

            try {
                await Promise.all([
                    this.$store.data.revalidate('collections'),
                    this.$store.data.revalidate('projects'),
                    this.$store.data.revalidate('properties'),
                    this.$store.data.revalidate('articles'),
                ]);
            } catch {
                // keep cached data
            }
        },

        get collections() {
            const term = this.search.trim();
            const pages = this.$store.data.collections;

            return term
                ? pages.filter((page) => `${page.title ?? ''} ${page.slug ?? ''}`.includes(term))
                : pages;
        },

        isTemp,

        /** Records not already on the page, grouped for the picker. */
        get itemOptions() {
            return linkOptions(this.$store.data, { kinds: ITEM_KINDS, exclude: this.form.items });
        },

        itemLabel(value) {
            return labelForValue(this.$store.data, value);
        },

        itemKind(value) {
            return KIND_LABELS[String(value).split(':')[0]] ?? '';
        },

        /** How many records a page holds, for its card. */
        itemCount(page) {
            return (page.items ?? []).length;
        },

        addPicked() {
            if (!this.picked || this.form.items.includes(this.picked)) {
                return;
            }

            this.form.items = [...this.form.items, this.picked];
            this.picked = '';
        },

        move(index, offset) {
            this.form.items = moveItem(this.form.items, index, offset);
        },

        removeItem(index) {
            this.form.items = this.form.items.filter((_, position) => position !== index);
        },

        /**
         * The address follows the title until someone writes one by hand, after
         * which it is left alone — a published link must not move on its own.
         */
        suggestSlug() {
            if (!this.editingId && !this.slugTouched) {
                this.form.slug = toSlug(this.form.title);
            }
        },

        slugTouched: false,

        openCreate() {
            this.panel = true;
            this.editingId = null;
            this.errors = {};
            this.picked = '';
            this.slugTouched = false;
            this.form = blankForm();
        },

        openEdit(record) {
            this.panel = true;
            this.editingId = record.id;
            this.errors = {};
            this.picked = '';
            this.slugTouched = true;
            this.form = {
                title: record.title ?? '',
                slug: record.slug ?? '',
                description: record.description ?? '',
                items: [...(record.items ?? [])],
            };
        },

        save() {
            this.errors = {};

            if (!this.form.title.trim()) {
                this.errors.title = 'عنوان الصفحة مطلوب.';
            }

            if (!this.form.slug.trim()) {
                this.errors.slug = 'رابط الصفحة مطلوب.';
            } else if (!isValidSlug(this.form.slug)) {
                this.errors.slug = 'الرابط يقبل الحروف والأرقام والشرطة (-) فقط، بلا مسافات.';
            } else if (this.takenSlug()) {
                this.errors.slug = 'هذا الرابط مستخدم في صفحة أخرى.';
            }

            if (Object.keys(this.errors).length) {
                return;
            }

            const attributes = {
                title: this.form.title,
                slug: this.form.slug,
                description: this.form.description || null,
                items: [...this.form.items],
            };

            if (this.editingId) {
                this.$store.data.updateRecord('collections', this.editingId, attributes);
            } else {
                this.$store.data.createRecord('collections', attributes);
            }

            this.closePanel();
        },

        /**
         * The server has the last word on uniqueness, but catching it here keeps
         * a doomed payload out of the outbox while the panel is offline.
         */
        takenSlug() {
            return this.$store.data.collections.some(
                (page) => page.id !== this.editingId && page.slug === this.form.slug
            );
        },

        remove(record) {
            if (confirm(`هل أنت متأكد من حذف صفحة «${record.title}»؟`)) {
                this.$store.data.deleteRecord('collections', record.id);
            }
        },

        closePanel() {
            this.panel = false;
            this.editingId = null;
            this.errors = {};
        },
    };
}
