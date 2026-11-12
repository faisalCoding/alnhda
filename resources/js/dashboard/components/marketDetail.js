import { request } from '../api.js';

const NUMBER = new Intl.NumberFormat('ar-SA');
const DECIMAL = new Intl.NumberFormat('ar-SA', { maximumFractionDigits: 1 });

/** The filters the deeds open on: the latest year, newest sale first. */
export function emptyDeedFilters() {
    return {
        district: '',
        area_min: '',
        area_max: '',
        price_min: '',
        price_max: '',
        quarters: 4,
        sort: 'date',
        direction: 'desc',
        page: 1,
    };
}

/**
 * The part of the market prices screen that reads deeds one by one, and sets
 * the company's own projects beside them. It exists only for the cities whose
 * deeds are kept; for any other city the screen is told there are none and
 * these stay empty.
 *
 * Spread into the page component, so everything here is a plain property or a
 * method: a getter would be read once, at the spread, and frozen.
 */
export default function marketDetail() {
    return {
        deeds: null,
        deedsLoading: false,
        deedsError: '',
        deedFilters: emptyDeedFilters(),

        projects: null,
        projectsError: '',
        placing: null,

        /** The city and classification the detail was last read for. */
        detailKey: '',

        /**
         * Reads the deeds and the projects again when the city or the
         * classification has changed, and only then: opening a district or
         * switching the chart's metric leaves them as they are.
         */
        async refreshDetail({ force = false } = {}) {
            if (!this.data?.deeds_available) {
                this.deeds = null;
                this.projects = null;
                this.detailKey = '';

                return;
            }

            const key = `${this.city}|${this.classification}`;

            if (!force && key === this.detailKey) {
                return;
            }

            this.detailKey = key;
            this.deedFilters = { ...emptyDeedFilters(), quarters: this.deedFilters.quarters };

            await Promise.all([this.loadDeeds(), this.loadProjects()]);
        },

        /** The deeds request as a query string, empty filters left out. */
        deedsQuery() {
            const query = new URLSearchParams({ city: this.city });

            if (this.classification) {
                query.set('classification', this.classification);
            }

            for (const [name, value] of Object.entries(this.deedFilters)) {
                if (value !== '' && value !== null && value !== undefined) {
                    query.set(name, value);
                }
            }

            return query.toString();
        },

        async loadDeeds() {
            this.deedsLoading = true;
            this.deedsError = '';

            try {
                const payload = await request('GET', `/api/market-prices/deeds?${this.deedsQuery()}`);
                this.deeds = payload?.data ?? null;
            } catch (error) {
                this.deedsError = error.errors ? Object.values(error.errors)[0]?.[0] ?? error.message : error.message;
            }

            this.deedsLoading = false;
        },

        /** A changed filter starts again from the first page. */
        async filterDeeds() {
            this.deedFilters.page = 1;
            await this.loadDeeds();
        },

        async clearDeedFilters() {
            this.deedFilters = emptyDeedFilters();
            await this.loadDeeds();
        },

        /** Sorts by a column, and the other way on a second press. */
        async sortDeeds(column) {
            if (this.deedFilters.sort === column) {
                this.deedFilters.direction = this.deedFilters.direction === 'desc' ? 'asc' : 'desc';
            } else {
                this.deedFilters.sort = column;
                this.deedFilters.direction = 'desc';
            }

            await this.filterDeeds();
        },

        sortMark(column) {
            if (this.deedFilters.sort !== column) {
                return '';
            }

            return this.deedFilters.direction === 'desc' ? ' ↓' : ' ↑';
        },

        async turnDeedPage(step) {
            const page = this.deedFilters.page + step;

            if (page < 1 || page > (this.deeds?.pages ?? 1)) {
                return;
            }

            this.deedFilters.page = page;
            await this.loadDeeds();
        },

        /** Narrows the deeds to the district opened in the table above them. */
        async followDistrict(key) {
            if (!this.deeds) {
                return;
            }

            this.deedFilters.district = key ?? '';
            await this.filterDeeds();
        },

        /** «يناير 2026» out of «2026-01». */
        monthLabel(key) {
            const [year, month] = String(key ?? '').split('-').map(Number);

            if (!year || !month) {
                return '';
            }

            return new Date(Date.UTC(year, month - 1, 1)).toLocaleDateString('ar-SA-u-ca-gregory', {
                month: 'long',
                year: 'numeric',
                timeZone: 'UTC',
            });
        },

        monthHeight(point) {
            const peak = Math.max(1, ...(this.deeds?.monthly ?? []).map((month) => month.median ?? 0));
            const value = point.median ?? 0;

            return `${Math.max(value ? 2 : 0, Math.round((value / peak) * 100))}%`;
        },

        /** A price bracket in words: «أقل من ٢٥٠ ألف», «٥ مليون فأكثر». */
        bracketLabel(bracket) {
            const short = (amount) =>
                amount >= 1e6 ? `${DECIMAL.format(amount / 1e6)} مليون` : `${NUMBER.format(amount / 1e3)} ألف`;

            if (!bracket.from) {
                return `أقل من ${short(bracket.to)}`;
            }

            return bracket.to ? `${short(bracket.from)} – ${short(bracket.to)}` : `${short(bracket.from)} فأكثر`;
        },

        /** A bracket's bar, scaled against the fullest bracket. */
        bracketWidth(bracket) {
            const peak = Math.max(1, ...(this.deeds?.distribution ?? []).map((item) => item.deals));

            return `${Math.round((bracket.deals / peak) * 100)}%`;
        },

        async loadProjects() {
            this.projectsError = '';

            try {
                const payload = await request('GET', `/api/market-prices/projects?city=${encodeURIComponent(this.city)}`);
                this.projects = payload?.data ?? null;
            } catch (error) {
                this.projectsError = error.message;
            }
        },

        /** Places a project in a district, or takes it out of one. */
        async placeProject(project, district) {
            this.placing = project.id;
            this.projectsError = '';

            try {
                const payload = await request('PUT', `/api/market-prices/projects/${project.id}`, {
                    city: this.city,
                    district: district || null,
                });
                this.projects = payload?.data ?? this.projects;
            } catch (error) {
                this.projectsError = error.errors?.district?.[0] ?? error.message;
            }

            this.placing = null;
        },

        /**
         * The market figures a project is read against: its district's when
         * it has been placed in one, the city's otherwise.
         */
        marketFor(project) {
            return project.market?.district ?? project.market?.city ?? null;
        },

        /**
         * How far the project's price per metre sits from the market's middle
         * one, in percent. Nothing where either side is missing, or where the
         * market is too few deeds to be a market.
         */
        gap(project) {
            const market = this.marketFor(project);

            if (!project.rate || !market?.median || market.deals < 5) {
                return null;
            }

            const percent = Math.round(((project.rate - market.median) / market.median) * 100);

            return {
                direction: percent > 0 ? 'above' : percent < 0 ? 'below' : 'level',
                label:
                    percent === 0
                        ? 'عند وسيط السوق'
                        : `${NUMBER.format(Math.abs(percent))}٪ ${percent > 0 ? 'فوق' : 'تحت'} وسيط السوق`,
            };
        },
    };
}
