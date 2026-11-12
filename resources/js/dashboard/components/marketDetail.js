import { request } from '../api.js';
import { load, save } from '../storage.js';

const COMPARISON_SOURCES = ['ministry', 'manual'];

/** The kinds offered before the authority's list has been loaded. Mirrors MarketPlaces::USUAL_KINDS. */
const FALLBACK_KINDS = ['شقة', 'فيلا', 'دور', 'دوبلكس', 'أرض', 'عمارة'];

function storedComparisonSource() {
    const value = load('market.comparisonSource', 'ministry');

    return COMPARISON_SOURCES.includes(value) ? value : 'ministry';
}

function emptyManualForm() {
    return {
        name: '',
        project_name: '',
        city: 'جدة',
        district: '',
        kind: 'شقة',
        area: '',
        price: '',
    };
}

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

        /** ministry keeps the deeds; manual reads the units typed in below. */
        comparisonSource: storedComparisonSource(),
        manualUnits: [],
        manualUnitsError: '',
        manualErrors: {},
        manualForm: emptyManualForm(),
        savingManualUnit: false,

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

                // The ministry's deeds are not kept for this city. The manual
                // source does not need them, so the company's units are still read.
                if (this.comparisonSource !== 'manual') {
                    this.projects = null;
                    this.detailKey = '';

                    return;
                }

                await this.loadProjects();

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

        /** The comparison request: the city, and which market the report reads. */
        projectsQuery() {
            const query = new URLSearchParams({
                city: this.city,
                source: COMPARISON_SOURCES.includes(this.comparisonSource) ? this.comparisonSource : 'ministry',
            });

            return query.toString();
        },

        async loadProjects() {
            this.projectsError = '';

            try {
                const payload = await request('GET', `/api/market-prices/projects?${this.projectsQuery()}`);
                this.projects = payload?.data ?? null;

                if (COMPARISON_SOURCES.includes(this.projects?.comparison_source)) {
                    this.comparisonSource = this.projects.comparison_source;
                }
            } catch (error) {
                this.projectsError = error.message;
            }
        },

        /**
         * Switches the report between the ministry's deeds and the units
         * entered by hand, and keeps the choice for the next visit.
         */
        async setComparisonSource(source) {
            if (!COMPARISON_SOURCES.includes(source) || source === this.comparisonSource) {
                return;
            }

            this.comparisonSource = source;
            save('market.comparisonSource', source);
            await this.loadProjects();
        },

        async loadManualUnits() {
            this.manualUnitsError = '';

            try {
                const payload = await request('GET', '/api/market-prices/manual-units');
                this.manualUnits = payload?.data ?? [];
            } catch (error) {
                this.manualUnitsError = error.message;
            }
        },

        manualKindOptions() {
            return this.projects?.kinds?.length ? this.projects.kinds : FALLBACK_KINDS;
        },

        manualError(field) {
            return this.manualErrors?.[field]?.[0] ?? '';
        },

        async saveManualUnit() {
            if (this.savingManualUnit) {
                return;
            }

            this.savingManualUnit = true;
            this.manualUnitsError = '';
            this.manualErrors = {};

            try {
                await request('POST', '/api/market-prices/manual-units', {
                    name: this.manualForm.name,
                    project_name: this.manualForm.project_name || null,
                    city: this.manualForm.city,
                    district: this.manualForm.district,
                    kind: this.manualForm.kind,
                    area: this.manualForm.area,
                    price: this.manualForm.price,
                });
                this.manualForm = emptyManualForm();
                await this.loadManualUnits();

                if (this.comparisonSource === 'manual') {
                    await this.loadProjects();
                }
            } catch (error) {
                this.manualErrors = error.errors ?? {};
                this.manualUnitsError = error.errors ? '' : error.message;
            }

            this.savingManualUnit = false;
        },

        async removeManualUnit(unit) {
            if (!confirm(`حذف «${unit.name}»؟`)) {
                return;
            }

            this.manualUnitsError = '';

            try {
                await request('DELETE', `/api/market-prices/manual-units/${unit.id}`);
                await this.loadManualUnits();

                if (this.comparisonSource === 'manual') {
                    await this.loadProjects();
                }
            } catch (error) {
                this.manualUnitsError = error.message;
            }
        },

        /** «٥ صفقة» from the deeds, «٥ وحدة» from the units entered by hand. */
        similarCaption(similar, project) {
            if (!similar) {
                return '';
            }

            const noun = this.comparisonSource === 'manual' ? 'وحدة' : 'صفقة';

            return `${NUMBER.format(similar.deals)} ${noun} · ${this.scopeNote(similar, project)}`;
        },

        /** Places a project in a district, or takes it out of one. */
        async placeProject(project, district) {
            this.placing = project.id;
            this.projectsError = '';

            try {
                const payload = await request('PUT', `/api/market-prices/projects/${project.id}`, {
                    city: this.city,
                    district: district || null,
                    source: this.comparisonSource,
                });
                this.projects = payload?.data ?? this.projects;
            } catch (error) {
                this.projectsError = error.errors?.district?.[0] ?? error.message;
            }

            this.placing = null;
        },

        /** Says what kind of property a unit is, or goes back to guessing. */
        async setUnitKind(unit, kind) {
            this.placing = `unit-${unit.id}`;
            this.projectsError = '';

            try {
                const payload = await request('PUT', `/api/market-prices/units/${unit.id}`, {
                    city: this.city,
                    kind: kind || null,
                    source: this.comparisonSource,
                });
                this.projects = payload?.data ?? this.projects;
            } catch (error) {
                this.projectsError = error.errors?.kind?.[0] ?? error.message;
            }

            this.placing = null;
        },

        /** How many units a project has, in words, for its heading row. */
        unitCount(project) {
            const count = project.units?.length ?? 0;

            return count ? `${NUMBER.format(count)} وحدة` : 'لا وحدات';
        },

        /**
         * Where a market figure is from, in a few words: the district or the
         * whole city, and for the authority's figures which quarter.
         */
        scopeNote(figure, project) {
            if (!figure) {
                return '';
            }

            const place = figure.scope === 'district' ? `حي ${project.district_label}` : 'المدينة كلها';

            return figure.quarter ? `${place} · ${this.quarterLabel(figure.quarter)}` : place;
        },

        /**
         * How far a unit's price per metre sits from what its kind of
         * property sold for, in percent. Nothing where the unit has no price,
         * no kind, or the market for that kind is too few sales to be one.
         */
        gap(unit) {
            const market = unit.market?.kind;

            if (!unit.rate || !market?.average || market.deals < 5) {
                return null;
            }

            const percent = Math.round(((unit.rate - market.average) / market.average) * 100);

            return {
                direction: percent > 0 ? 'above' : percent < 0 ? 'below' : 'level',
                label:
                    percent === 0
                        ? 'عند متوسط السوق'
                        : `${NUMBER.format(Math.abs(percent))}٪ ${percent > 0 ? 'فوق' : 'تحت'} متوسط السوق`,
            };
        },
    };
}
