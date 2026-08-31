import { request } from '../api';

/** جوجل يقصّ ما بعد هذين الحدّين تقريباً، فالعدّاد يحذّر قبل الوصول إليهما. */
const TITLE_LIMIT = 60;
const DESCRIPTION_LIMIT = 155;

const RECORD_TYPES = [
    { key: 'project', label: 'المشاريع' },
    { key: 'article', label: 'المقالات' },
    { key: 'properties', label: 'الوحدات' },
    { key: 'collection', label: 'الصفحات المجمّعة' },
];

const emptyForm = () => ({
    title: '',
    description: '',
    image_path: '',
    image_url: '',
    og_type: '',
    noindex: false,
});

export default function seoPage() {
    return {
        tab: 'pages',
        loading: true,
        saving: false,
        uploading: false,
        faviconUploading: false,
        error: '',
        saved: false,
        formErrors: {},

        siteName: '',
        socialSize: [1200, 630],
        defaults: {},
        pages: [],

        recordTypes: RECORD_TYPES,
        recordType: 'project',
        recordSearch: '',
        records: [],
        recordsLoading: false,

        // ما يجري تحريره الآن: الافتراضات، أو صفحة ثابتة، أو سجلّ بعينه.
        target: null,
        form: emptyForm(),

        titleLimit: TITLE_LIMIT,
        descriptionLimit: DESCRIPTION_LIMIT,

        async init() {
            await this.load();
            this.loading = false;
        },

        async load() {
            try {
                const payload = await request('GET', '/api/seo');
                const data = payload?.data ?? {};

                this.defaults = data.defaults ?? {};
                this.pages = data.pages ?? [];
                this.siteName = data.site_name ?? '';
                this.socialSize = data.social_size ?? [1200, 630];

                if (this.pages.length > 0) {
                    this.selectPage(this.pages[0]);
                }
            } catch (e) {
                this.error = e.message;
            }
        },

        // ── الاختيار ─────────────────────────────────────────────────────────

        selectDefaults() {
            this.tab = 'defaults';
            this.target = { kind: 'defaults', label: 'الافتراضات العامة', url: null, auto: {} };
            this.form = {
                ...emptyForm(),
                title: this.defaults.seo_default_title ?? '',
                description: this.defaults.seo_default_description ?? '',
                image_path: this.defaults.seo_default_image_path ?? '',
                image_url: this.defaults.seo_default_image_url ?? '',
            };
            this.resetFeedback();
        },

        selectPage(page) {
            this.target = { kind: 'page', key: page.route_name, label: page.label, url: page.url, auto: page.auto ?? {} };
            this.form = {
                title: page.title ?? '',
                description: page.description ?? '',
                image_path: page.image_path ?? '',
                image_url: page.image_url ?? '',
                og_type: page.og_type ?? '',
                noindex: !!page.noindex,
            };
            this.resetFeedback();
        },

        selectRecord(record) {
            this.target = {
                kind: 'record',
                key: `${record.type}/${record.id}`,
                label: record.name,
                url: record.url,
                auto: record.auto ?? {},
            };
            this.form = {
                title: record.title ?? '',
                description: record.description ?? '',
                image_path: record.image_path ?? '',
                image_url: record.image_url ?? '',
                og_type: '',
                noindex: !!record.noindex,
            };
            this.resetFeedback();
        },

        resetFeedback() {
            this.formErrors = {};
            this.saved = false;
            this.error = '';
        },

        // ── السجلات ──────────────────────────────────────────────────────────

        async loadRecords() {
            this.recordsLoading = true;

            try {
                const query = new URLSearchParams({ type: this.recordType, search: this.recordSearch });
                this.records = (await request('GET', `/api/seo/records?${query}`))?.data ?? [];
            } catch (e) {
                this.error = e.message;
                this.records = [];
            }

            this.recordsLoading = false;
        },

        async openRecords() {
            this.tab = 'records';

            if (this.records.length === 0) {
                await this.loadRecords();
            }
        },

        // ── المعاينة ─────────────────────────────────────────────────────────
        // نفس ترتيب الأولوية الذي يطبّقه الخادم، وإلا عرضت المعاينة شيئاً
        // ويظهر للزائر شيء آخر.

        get previewTitle() {
            return this.form.title
                || this.target?.auto?.title
                || this.defaults.seo_default_title
                || this.siteName;
        },

        get previewDescription() {
            return this.form.description
                || this.target?.auto?.description
                || this.defaults.seo_default_description
                || '';
        },

        get previewImage() {
            return this.form.image_url
                || this.target?.auto?.image_url
                || this.defaults.seo_default_image_url
                || '/img/KNicon.png';
        },

        get previewUrl() {
            return this.target?.url || window.location.origin;
        },

        get previewHost() {
            try {
                return new URL(this.previewUrl).host;
            } catch {
                return '';
            }
        },

        /** المسار كما يعرضه جوجل: مفتّتاً بالسهام لا كرابط خام. */
        get previewCrumbs() {
            try {
                const path = new URL(this.previewUrl).pathname.split('/').filter(Boolean);

                return [this.previewHost, ...path.map((part) => decodeURIComponent(part))].join(' › ');
            } catch {
                return this.previewHost;
            }
        },

        counterClass(value, limit) {
            const length = (value ?? '').length;

            if (length === 0) {
                return 'text-zinc-400';
            }

            return length > limit ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400';
        },

        // ── الحفظ ────────────────────────────────────────────────────────────

        async uploadImage(event) {
            const file = event.target.files?.[0];

            if (!file) {
                return;
            }

            this.uploading = true;
            this.resetFeedback();

            const body = new FormData();
            body.append('image', file);

            try {
                const data = (await request('POST', '/api/seo/image', body))?.data ?? {};
                this.form.image_path = data.image_path ?? '';
                this.form.image_url = data.image_url ?? '';
            } catch (e) {
                this.error = e.errors?.image?.[0] ?? e.message;
            }

            this.uploading = false;
            event.target.value = '';
        },

        /**
         * الأيقونة تُحفظ فور رفعها لا مع بقية النموذج: هي إعداد قائم بذاته،
         * وربطها بزر «حفظ» يجعل رفعها بلا أثر حتى يُضغط.
         */
        async uploadFavicon(event) {
            const file = event.target.files?.[0];

            if (!file) {
                return;
            }

            this.faviconUploading = true;
            this.resetFeedback();

            const body = new FormData();
            body.append('favicon', file);

            try {
                this.defaults = (await request('POST', '/api/seo/favicon', body))?.data ?? this.defaults;
                this.saved = true;
            } catch (e) {
                this.error = e.errors?.favicon?.[0] ?? e.message;
            }

            this.faviconUploading = false;
            event.target.value = '';
        },

        clearImage() {
            this.form.image_path = '';
            this.form.image_url = '';
        },

        async save() {
            if (!this.target) {
                return;
            }

            this.saving = true;
            this.resetFeedback();

            try {
                if (this.target.kind === 'defaults') {
                    await this.saveDefaults();
                } else if (this.target.kind === 'page') {
                    await this.savePage();
                } else {
                    await this.saveRecord();
                }

                this.saved = true;
            } catch (e) {
                this.error = e.message;
                this.formErrors = e.errors ?? {};
            }

            this.saving = false;
        },

        async saveDefaults() {
            const payload = await request('PUT', '/api/seo/defaults', {
                seo_default_title: this.form.title || null,
                seo_default_description: this.form.description || null,
                seo_default_image_path: this.form.image_path || null,
            });

            this.defaults = payload?.data ?? this.defaults;
        },

        async savePage() {
            const payload = await request('PUT', `/api/seo/pages/${this.target.key}`, {
                title: this.form.title || null,
                description: this.form.description || null,
                image_path: this.form.image_path || null,
                og_type: this.form.og_type || null,
                noindex: this.form.noindex,
            });

            const updated = payload?.data;
            this.pages = this.pages.map((page) => (page.route_name === updated.route_name ? updated : page));
        },

        async saveRecord() {
            const payload = await request('PUT', `/api/seo/records/${this.target.key}`, {
                title: this.form.title || null,
                description: this.form.description || null,
                image_path: this.form.image_path || null,
                noindex: this.form.noindex,
            });

            const updated = payload?.data;
            this.records = this.records.map(
                (record) => (record.type === updated.type && record.id === updated.id ? updated : record)
            );
        },
    };
}
