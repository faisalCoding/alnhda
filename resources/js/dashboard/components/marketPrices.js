import { request, uploadWithProgress } from '../api.js';
import marketDetail from './marketDetail.js';

const NUMBER = new Intl.NumberFormat('ar-SA');
const DECIMAL = new Intl.NumberFormat('ar-SA', { maximumFractionDigits: 1 });
const QUARTER_NAMES = ['', 'الأول', 'الثاني', 'الثالث', 'الرابع'];

/**
 * A district with fewer deeds than this in a quarter has no price to speak of:
 * its median is one or two sales, and it moves by half from one quarter to the
 * next without the market having moved at all.
 */
export const THIN_MARKET = 5;

/**
 * Reads figures that were worked out when each quarter's file was uploaded.
 * The screen never opens a file itself: an upload hands the file to the server
 * and the screen is read again once it has been counted.
 */
export default function marketPricesPage() {
    return {
        ...marketDetail(),

        loading: true,
        error: '',
        data: null,

        quarter: '',
        classification: '',
        band: '',
        city: '',
        district: '',
        type: '',

        metric: 'median',
        search: '',
        sort: 'deals',
        hideThin: true,
        thinMarket: THIN_MARKET,

        uploads: [],
        uploading: false,
        dragging: false,

        checking: false,
        checkMessage: '',

        async init() {
            await this.load();
            this.loading = false;
        },

        async load() {
            this.error = '';

            const query = new URLSearchParams();

            for (const name of ['quarter', 'classification', 'band', 'city', 'district', 'type']) {
                if (this[name]) {
                    query.set(name, this[name]);
                }
            }

            try {
                const payload = await request('GET', `/api/market-prices?${query}`);
                this.data = payload?.data ?? null;

                // The server answers with what it actually used: a quarter that
                // was never imported or a classification no file carries falls
                // back there, and the controls should show where it landed.
                const filters = this.data?.filters;

                if (filters) {
                    this.quarter = filters.quarter;
                    this.classification = filters.classification;
                    this.band = filters.band;
                    this.city = filters.city;
                    this.district = filters.district ?? '';
                }

                // A kind of property the chosen city or quarter does not have
                // comes back unselected.
                this.type = this.data?.types?.selected ?? '';
            } catch (error) {
                this.error = error.message;

                return;
            }

            await this.refreshDetail();
        },

        async setClassification(value) {
            this.classification = value;
            await this.load();
        },

        async setBand(value) {
            this.band = value;
            await this.load();
        },

        async setCity(value) {
            this.city = value;
            this.district = '';
            this.type = '';
            this.search = '';
            await this.load();
        },

        async openDistrict(key) {
            this.district = this.district === key ? '' : key;
            await this.load();
            await this.followDistrict(this.district);
        },

        async openType(name) {
            this.type = this.type === name ? '' : name;
            await this.load();
        },

        /**
         * Asks the open data portal now which quarters it has published,
         * rather than waiting for the weekly check.
         */
        async check() {
            if (this.checking) {
                return;
            }

            this.checking = true;
            this.checkMessage = '';

            try {
                const payload = await request('POST', '/api/market-prices/check');
                const waiting = (payload?.data?.waiting ?? 0) + (payload?.data?.indicators_waiting ?? 0);

                this.checkMessage = waiting
                    ? `على المنصة ${this.number(waiting)} من الملفات لم يُرفع بعد.`
                    : 'كل ما نُشر على المنصة مرفوع هنا.';

                await this.load();
            } catch (error) {
                this.checkMessage = error.message;
            }

            this.checking = false;
        },

        /** Files let go of anywhere on the screen are uploaded like chosen ones. */
        async drop(event) {
            this.dragging = false;
            await this.upload({ target: { files: event.dataTransfer?.files ?? [] } });
        },

        /**
         * Sends the chosen files one after another. A quarter is counted on the
         * server in a couple of seconds, and one at a time keeps a failure
         * attached to the file that caused it.
         */
        async upload(event) {
            const files = Array.from(event.target.files ?? []);
            event.target.value = '';

            if (!files.length || this.uploading) {
                return;
            }

            this.uploading = true;
            this.uploads = files.map((file) => ({ name: file.name, status: 'waiting', progress: 0, message: '' }));

            for (const [index, file] of files.entries()) {
                const entry = this.uploads[index];
                const form = new FormData();
                form.append('file', file);
                entry.status = 'uploading';

                try {
                    const payload = await uploadWithProgress('/api/market-prices/import', form, (progress) => {
                        entry.progress = progress;
                    });

                    entry.status = 'done';
                    entry.message = this.importSummary(payload?.data?.quarters ?? [], payload?.data?.kind);
                } catch (error) {
                    entry.status = 'failed';
                    entry.message = error.errors?.file?.[0] ?? error.message;
                }
            }

            this.uploading = false;
            // Back to the newest quarter, which is usually the one just added,
            // and the deeds are read again whatever the city: they changed.
            this.quarter = '';
            this.detailKey = '';
            await this.load();
        },

        /** What a file turned out to be and how much was read from it. */
        importSummary(quarters, kind = 'sales') {
            return quarters
                .map((quarter) => {
                    const source = kind === 'indicators' ? `مؤشرات نوع العقار، ${quarter.region} — ` : '';

                    return `${source}${this.quarterLabel(quarter)}: ${this.number(quarter.deals)} صفقة`;
                })
                .join(' · ');
        },

        get hasData() {
            return Boolean(this.data?.has_data);
        },

        /** Newest first, the order a person picks a quarter in. */
        get quarters() {
            return [...(this.data?.quarters ?? [])].reverse();
        },

        get waiting() {
            return this.data?.waiting ?? [];
        },

        get classifications() {
            return this.data?.classifications ?? [];
        },

        get bands() {
            return this.data?.bands ?? [];
        },

        /**
         * When the list of published files was last brought up to date,
         * whether by this server or by a computer that sent it.
         */
        get catalogueCheckedAt() {
            return this.day(this.data?.catalogue_checked_at);
        },

        /** The authority's figures by kind of property for the chosen city. */
        get types() {
            return this.data?.types ?? null;
        },

        /**
         * The quarter the type figures are for, named only when it is not the
         * one the rest of the screen is on: the authority publishes later than
         * the ministry, so the two are often a quarter apart.
         */
        get typesQuarterNote() {
            const shown = this.types?.quarter;

            if (!shown) {
                return '';
            }

            return shown === this.data?.filters?.quarter
                ? ''
                : `أقرب ربع نشرته الهيئة: ${this.quarterLabel(shown)}.`;
        },

        /**
         * Quarters counted before area bands and price ranges were kept. They
         * still draw, but have nothing to show under a band until their file
         * is uploaded again.
         */
        get outdated() {
            return (this.data?.quarters ?? []).filter((quarter) => quarter.outdated);
        },

        /** Quarters whose file the ministry replaced after it was uploaded. */
        get changed() {
            return (this.data?.quarters ?? []).filter((quarter) => quarter.file_changed);
        },

        get comparedWith() {
            const key = this.data?.filters?.compared_with;

            return key ? this.quarterLabel(key) : '';
        },

        /**
         * The districts of the chosen city as the table shows them: searched,
         * thinned of the ones too quiet to price, and ordered.
         */
        get districts() {
            const term = this.search.trim();

            const rows = (this.data?.districts ?? []).filter((row) => {
                if (term && !row.label.includes(term)) {
                    return false;
                }

                return !this.hideThin || row.deals >= THIN_MARKET || row.key === this.district;
            });

            const value = {
                deals: (row) => row.deals,
                median: (row) => row.median,
                value: (row) => row.value,
                change: (row) => this.percent(row.median, row.previous_median) ?? -Infinity,
            }[this.sort];

            return rows.sort((a, b) => value(b) - value(a));
        },

        get hiddenDistricts() {
            return (this.data?.districts ?? []).filter((row) => row.deals < THIN_MARKET).length;
        },

        number(value) {
            return NUMBER.format(Math.round(value ?? 0));
        },

        /** A sum of riyals as a person says it, not as fourteen digits. */
        money(value) {
            const amount = Number(value ?? 0);

            if (amount >= 1e9) {
                return `${DECIMAL.format(amount / 1e9)} مليار`;
            }

            if (amount >= 1e6) {
                return `${DECIMAL.format(amount / 1e6)} مليون`;
            }

            return NUMBER.format(Math.round(amount));
        },

        /** A price per square metre, or a dash where nothing was sold. */
        rate(value) {
            return value === null || value === undefined ? '—' : NUMBER.format(Math.round(value));
        },

        /**
         * The middle half of prices per metre, low to high. A dash where the
         * quarter was counted before ranges were kept, or nothing was sold.
         */
        range(point) {
            if (!point?.lower || !point?.upper) {
                return '—';
            }

            return `${this.rate(point.lower)} – ${this.rate(point.upper)}`;
        },

        /** The lowest and highest price per metre the authority reported. */
        extremes(row) {
            if (!row?.lowest || !row?.highest) {
                return '—';
            }

            return `${this.rate(row.lowest)} – ${this.rate(row.highest)}`;
        },

        day(iso) {
            return iso ? new Date(iso).toLocaleDateString('ar-SA', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
        },

        /** Accepts a «2026-1» key or anything carrying a year and a quarter. */
        quarterLabel(quarter) {
            const [year, number] =
                typeof quarter === 'string' ? quarter.split('-').map(Number) : [quarter?.year, quarter?.quarter];

            return year && QUARTER_NAMES[number] ? `الربع ${QUARTER_NAMES[number]} ${year}` : '';
        },

        shortQuarter(point) {
            return point ? `${point.year} ر${point.quarter}` : '';
        },

        /** Change in percent, or null where there is nothing to compare with. */
        percent(current, previous) {
            if (!previous || current === null || current === undefined) {
                return null;
            }

            return Math.round(((current - previous) / previous) * 100);
        },

        /**
         * Change against the same quarter a year earlier, ready to print. A
         * place with no earlier figure gets nothing rather than an infinity.
         */
        change(current, previous) {
            const percent = this.percent(current, previous);

            if (percent === null) {
                return null;
            }

            return {
                direction: percent > 0 ? 'up' : percent < 0 ? 'down' : 'flat',
                label: `${percent > 0 ? '+' : ''}${NUMBER.format(percent)}٪`,
            };
        },

        changeClass(current, previous) {
            const direction = this.change(current, previous)?.direction;

            return {
                'text-primary-600 dark:text-primary-300': direction === 'up',
                'text-red-500': direction === 'down',
                'text-zinc-400': direction === 'flat',
            };
        },

        /**
         * The tallest bar of a chart, used to scale the rest. A chart draws
         * the metric chosen on the screen unless it names its own.
         */
        peak(series, metric = this.metric) {
            return Math.max(1, ...(series ?? []).map((point) => point[metric] ?? 0));
        },

        barHeight(series, point, metric = this.metric) {
            const value = point[metric] ?? 0;

            return `${Math.max(value ? 2 : 0, Math.round((value / this.peak(series, metric)) * 100))}%`;
        },

        /** What a bar says when pointed at, in the unit of its metric. */
        barLabel(point, metric = this.metric) {
            const value = {
                median: () => `${this.rate(point.median)} ر.س/م²`,
                average: () => `${this.rate(point.average)} ر.س/م² · ${this.number(point.deals)} صفقة`,
                deals: () => `${this.number(point.deals)} صفقة`,
                value: () => `${this.money(point.value)} ر.س`,
            }[metric]();

            return `${this.quarterLabel(point)} — ${value}`;
        },
    };
}
