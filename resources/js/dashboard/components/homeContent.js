import { ApiError, request } from '../api';
import { uuid } from '../ids';

const blankQuestion = () => ({ id: null, question: '', answer: '' });

/**
 * The words on the front of the site. Like the SEO screen this talks to the API
 * directly rather than through the offline store: it is a handful of fields
 * edited at a desk, not a record queued from a phone with no signal.
 */
export default function homeContentPage() {
    return {
        loading: true,
        saving: false,
        error: '',
        saved: false,

        hero: {},
        heroDefaults: {},
        heroImageUrl: '',
        heroImageIsUploaded: false,
        uploading: false,
        sections: [],
        guarantees: [],
        guaranteeDefaults: [],

        faq: [],
        faqDefaults: [],
        form: blankQuestion(),
        formErrors: {},
        showForm: false,

        async init() {
            await this.load();
            this.loading = false;
        },

        async load() {
            try {
                const payload = await request('GET', '/api/home-content');
                this.apply(payload?.data ?? {});
            } catch (error) {
                this.error = error.message;
            }
        },

        apply(data) {
            this.hero = { ...(data.hero ?? {}) };
            this.heroDefaults = data.hero_defaults ?? {};
            this.heroImageUrl = data.hero_image_url ?? '';
            this.heroImageIsUploaded = Boolean(data.hero_image_is_uploaded);
            this.sections = data.sections ?? [];
            this.guarantees = [...(data.home_guarantees ?? [])];
            this.guaranteeDefaults = data.guarantee_defaults ?? [];
            this.faq = data.faq ?? [];
            this.faqDefaults = data.faq_defaults ?? [];
        },

        /** What the page shows today for a field left empty here. */
        placeholderFor(key) {
            return this.heroDefaults[key] ?? '';
        },

        async uploadHeroImage(event) {
            const file = event.target.files?.[0];

            if (!file) {
                return;
            }

            this.uploading = true;
            this.error = '';

            const body = new FormData();
            body.append('image', file);

            try {
                const payload = await request('POST', '/api/home-content/hero-image', body);
                this.apply(payload?.data ?? {});
            } catch (error) {
                this.error = error.errors?.image?.[0] ?? error.message;
            } finally {
                this.uploading = false;
                event.target.value = '';
            }
        },

        async removeHeroImage() {
            if (!confirm('هل تريد حذف الصورة المرفوعة والعودة إلى صورة أول مشروع؟')) {
                return;
            }

            try {
                const payload = await request('DELETE', '/api/home-content/hero-image', null, { idempotencyKey: uuid() });
                this.apply(payload?.data ?? {});
            } catch (error) {
                this.error = error.message;
            }
        },

        toggleSection(key) {
            this.sections = this.sections.map((section) =>
                section.key === key ? { ...section, visible: !section.visible } : section
            );
        },

        get hiddenSections() {
            return this.sections.filter((section) => !section.visible).map((section) => section.key);
        },

        get usingProjectGuarantees() {
            return this.guarantees.length === 0;
        },

        addGuarantee() {
            this.guarantees.push('');
        },

        /** Starts the override from what the projects already say. */
        copyProjectGuarantees() {
            this.guarantees = [...this.guaranteeDefaults];
        },

        removeGuarantee(index) {
            this.guarantees.splice(index, 1);
        },

        async saveHero() {
            this.saving = true;
            this.error = '';
            this.saved = false;

            try {
                const payload = await request(
                    'PUT',
                    '/api/home-content',
                    {
                        ...this.hero,
                        home_guarantees: this.guarantees.map((text) => text.trim()).filter(Boolean),
                        hidden_home_sections: this.hiddenSections,
                    },
                    { idempotencyKey: uuid() }
                );

                this.apply(payload?.data ?? {});
                this.saved = true;
                setTimeout(() => (this.saved = false), 2500);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        // ---- questions ----

        get usingDerivedFaq() {
            return this.faq.length === 0;
        },

        openCreate() {
            this.form = blankQuestion();
            this.formErrors = {};
            this.showForm = true;
        },

        openEdit(entry) {
            this.form = { ...entry };
            this.formErrors = {};
            this.showForm = true;
        },

        async saveQuestion() {
            this.saving = true;
            this.formErrors = {};

            const body = { question: this.form.question, answer: this.form.answer };
            const isUpdate = Boolean(this.form.id);

            try {
                const payload = isUpdate
                    ? await request('PUT', `/api/home-content/faq/${this.form.id}`, body, { idempotencyKey: uuid() })
                    : await request('POST', '/api/home-content/faq', body, { idempotencyKey: uuid() });

                const saved = payload?.data;

                if (isUpdate) {
                    this.faq = this.faq.map((entry) => (entry.id === saved.id ? saved : entry));
                } else {
                    this.faq = [...this.faq, saved];
                }

                this.showForm = false;
            } catch (error) {
                if (error instanceof ApiError && error.errors) {
                    this.formErrors = Object.fromEntries(
                        Object.entries(error.errors).map(([field, messages]) => [field, messages[0]])
                    );
                } else {
                    this.error = error.message;
                }
            } finally {
                this.saving = false;
            }
        },

        async removeQuestion(entry) {
            if (!confirm(`هل أنت متأكد من حذف السؤال «${entry.question}»؟`)) {
                return;
            }

            try {
                await request('DELETE', `/api/home-content/faq/${entry.id}`, null, { idempotencyKey: uuid() });
                this.faq = this.faq.filter((candidate) => candidate.id !== entry.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        async move(entry, offset) {
            const from = this.faq.findIndex((candidate) => candidate.id === entry.id);
            const to = from + offset;

            if (from === -1 || to < 0 || to >= this.faq.length) {
                return;
            }

            const next = [...this.faq];
            next.splice(to, 0, ...next.splice(from, 1));
            this.faq = next;

            try {
                const payload = await request(
                    'POST',
                    '/api/home-content/faq/reorder',
                    { ids: next.map((candidate) => candidate.id) },
                    { idempotencyKey: uuid() }
                );

                this.apply(payload?.data ?? {});
            } catch (error) {
                this.error = error.message;
            }
        },

        /** Copies the answers the site writes for itself, to edit rather than start blank. */
        async importDerived() {
            this.saving = true;

            try {
                const payload = await request('POST', '/api/home-content/faq/import', null, { idempotencyKey: uuid() });
                this.apply(payload?.data ?? {});
            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },
    };
}
