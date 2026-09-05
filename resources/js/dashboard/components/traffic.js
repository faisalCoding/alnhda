import { request } from '../api.js';

const NUMBER = new Intl.NumberFormat('ar-SA');

/**
 * Reads rows the nightly jobs wrote. Nothing on this screen reaches Google or
 * the log file, so opening it costs the server two indexed queries.
 */
export default function trafficPage() {
    return {
        loading: true,
        error: '',
        days: 30,
        data: null,

        async init() {
            await this.load();
            this.loading = false;
        },

        async load() {
            this.error = '';

            try {
                const payload = await request('GET', `/api/traffic?days=${this.days}`);
                this.data = payload?.data ?? null;
            } catch (error) {
                this.error = error.message;
            }
        },

        async setRange(days) {
            this.days = days;
            await this.load();
        },

        /**
         * The header sits outside the block that waits for the data, so this is
         * read while `data` is still null on every first paint. Formatting it
         * here keeps that null out of the markup, where Alpine evaluates an
         * expression even when the element it belongs to is hidden.
         */
        get lastUpdated() {
            const pulledAt = this.data?.google?.pulled_at;

            return pulledAt ? new Date(pulledAt).toLocaleString('ar-SA') : '';
        },

        /**
         * How long ago the running day was last refreshed. Read as a phrase
         * rather than a timestamp: nobody reads «14:37» and works out whether
         * the number in front of them is stale.
         */
        get todayUpdated() {
            const updatedAt = this.data?.today?.updated_at;

            if (!updatedAt) {
                return '';
            }

            const minutes = Math.max(0, Math.round((Date.now() - new Date(updatedAt).getTime()) / 60000));

            if (minutes < 1) {
                return 'محدّث الآن';
            }

            if (minutes < 60) {
                return `محدّث قبل ${NUMBER.format(minutes)} دقيقة`;
            }

            const hours = Math.round(minutes / 60);

            return `محدّث قبل ${NUMBER.format(hours)} ساعة`;
        },

        number(value) {
            return NUMBER.format(value ?? 0);
        },

        /** Bytes as something a person reads, not a nine-digit number. */
        bytes(value) {
            const units = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
            let size = Number(value ?? 0);
            let unit = 0;

            while (size >= 1024 && unit < units.length - 1) {
                size /= 1024;
                unit++;
            }

            return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
        },

        /**
         * Change against the previous stretch of the same length. Growth from
         * nothing is not a percentage, so it is reported as new rather than as
         * an infinity.
         */
        change(current, previous) {
            if (!previous) {
                return current ? { direction: 'new', label: 'جديد' } : null;
            }

            const percent = Math.round(((current - previous) / previous) * 100);

            return {
                direction: percent > 0 ? 'up' : percent < 0 ? 'down' : 'flat',
                label: `${percent > 0 ? '+' : ''}${NUMBER.format(percent)}٪`,
            };
        },

        get timeline() {
            return this.data?.days ?? [];
        },

        /** The tallest bar in the chart, used to scale the rest. */
        get peak() {
            return Math.max(1, ...this.timeline.map((day) => day.users), ...this.timeline.map((day) => day.requests));
        },

        barHeight(value) {
            return `${Math.max(value ? 2 : 0, Math.round((value / this.peak) * 100))}%`;
        },

        shortDate(iso) {
            return new Date(iso).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short' });
        },

        /** A ranked list scaled against its own leader. */
        share(entry, list) {
            const top = Math.max(1, ...(list ?? []).map((item) => item.value));

            return `${Math.round((entry.value / top) * 100)}%`;
        },
    };
}
