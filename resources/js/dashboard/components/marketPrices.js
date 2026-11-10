import { request, uploadWithProgress } from '../api.js';

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
        loading: true,
        error: '',
        data: null,

        quarter: '',
        classification: '',
        city: '',
        district: '',

        metric: 'median',
        search: '',
        sort: 'deals',
        hideThin: true,
        thinMarket: THIN_MARKET,

        uploads: [],
        uploading: false,

        async init() {
            await this.load();
            this.loading = false;
        },

        async load() {
            this.error = '';

            const query = new URLSearchParams();

            for (const name of ['quarter', 'classification', 'city', 'district']) {
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
                    this.city = filters.city;
                    this.district = filters.district ?? '';
                }
            } catch (error) {
                this.error = error.message;
            }
        },

        async setClassification(value) {
            this.classification = value;
            await this.load();
        },

        async setCity(value) {
            this.city = value;
            this.district = '';
            this.search = '';
            await this.load();
        },

        async openDistrict(key) {
            this.district = this.district === key ? '' : key;
            await this.load();
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
                    entry.message = this.importSummary(payload?.data?.quarters ?? []);
                } catch (error) {
                    entry.status = 'failed';
                    entry.message = error.errors?.file?.[0] ?? error.message;
                }
            }

            this.uploading = false;
            // Back to the newest quarter, which is usually the one just added.
            this.quarter = '';
            await this.load();
        },

        importSummary(quarters) {
            return quarters
                .map((quarter) => `${this.quarterLabel(quarter)}: ${this.number(quarter.deals)} صفقة`)
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

        /** The tallest bar of a chart, used to scale the rest. */
        peak(series) {
            return Math.max(1, ...(series ?? []).map((point) => point[this.metric] ?? 0));
        },

        barHeight(series, point) {
            const value = point[this.metric] ?? 0;

            return `${Math.max(value ? 2 : 0, Math.round((value / this.peak(series)) * 100))}%`;
        },

        /** What a bar says when pointed at, in the unit of the chosen metric. */
        barLabel(point) {
            const value = {
                median: () => `${this.rate(point.median)} ر.س/م²`,
                deals: () => `${this.number(point.deals)} صفقة`,
                value: () => `${this.money(point.value)} ر.س`,
            }[this.metric]();

            return `${this.quarterLabel(point)} — ${value}`;
        },
    };
}
