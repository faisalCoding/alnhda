import { request, uploadWithProgress } from '../api.js';
import { USUAL_KINDS } from '../marketPlaces.js';
import { load, save } from '../storage.js';

/** Today as a date field writes it, in the browser's own day. */
function today() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/**
 * A unit seen on the market, before anything has been typed into it. The
 * fields are a listings file's columns, so a listing copied by hand and one
 * read from a file say the same things.
 */
export function emptyOfferForm() {
    return {
        source_site: '',
        listing_id: '',
        source_url: '',
        district: '',
        name: '',
        kind: 'شقة',
        price: '',
        area: '',
        rooms: '',
        bedrooms: '',
        bathrooms: '',
        street_width: '',
        facade: '',
        posted_on: '',
        listing_updated_on: '',
        observed_on: today(),
        duplicates: '',
        property_age: '',
        property_age_years: '',
        project_name: '',
    };
}

/**
 * What a unit's price can be measured against, and how many sales or offers
 * each needs before it is a market and not a coincidence. The hand-entered
 * offers are counted property by property and are fewer by nature, so they
 * need fewer.
 *
 * @type {Record<string, { label: string, figure: string, value: string, minimum: number }>}
 */
export const GAP_REFERENCES = {
    kind: { label: 'متوسط الهيئة لنفس النوع', figure: 'kind', value: 'average', minimum: 5 },
    deeds: { label: 'صفقات وزارة العدل المماثلة', figure: 'similar', value: 'median', minimum: 5 },
    offers: { label: 'العروض المضافة يدويًا', figure: 'offers', value: 'median', minimum: 3 },
};

/** Whether offers are read of about the unit's own size; on unless turned off on this browser. */
function storedOffersBySize() {
    return load('market.offersBySize', true) !== false;
}

/** The reference chosen on this browser before, or the authority's average. */
function storedGapReference() {
    const value = load('market.gapReference', 'kind');

    return Object.hasOwn(GAP_REFERENCES, value) ? value : 'kind';
}

/** The fields of the offer form, as the form names them. */
const OFFER_FIELDS = Object.keys(emptyOfferForm());

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
 * The part of the market prices screen that goes below the figures: the deeds
 * one by one, the units someone saw offered on the market and entered by hand,
 * and the company's own units set beside both. Deeds are kept only for some
 * cities; where there are none the deeds stay empty and the rest still reads.
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

        /** Units seen offered on the market and entered by hand, for the chosen city. */
        offers: null,
        offersError: '',
        offerErrors: {},
        offerForm: emptyOfferForm(),
        /** The unit the form is correcting; null while it is adding a new one. */
        editingOffer: null,
        savingOffer: false,

        /** What the «الفرق» column measures a unit against. */
        gapReference: storedGapReference(),

        /**
         * Whether a unit is set beside the offers of about its own size, or
         * beside every offer of its kind in the place whatever its size.
         */
        offersBySize: storedOffersBySize(),

        /** A listings file being read, and what reading it did. */
        importingListings: false,
        listingKind: '',
        listingReport: null,
        listingError: '',

        /** The city and classification the detail was last read for. */
        detailKey: '',

        /**
         * Reads what hangs off the city again when the city or the
         * classification has changed, and only then: opening a district or
         * switching the chart's metric leaves it as it is.
         *
         * The deeds are read only where they are kept. The company's units and
         * the hand-entered ones are read for any city: they are compared with
         * the authority's figures and with each other, not only with deeds.
         */
        async refreshDetail({ force = false } = {}) {
            const key = `${this.detailCity()}|${this.classification}`;

            if (!force && key === this.detailKey) {
                return;
            }

            this.detailKey = key;
            this.deedFilters = { ...emptyDeedFilters(), quarters: this.deedFilters.quarters };
            this.cancelOfferEdit();

            if (!this.data?.deeds_available) {
                this.deeds = null;
            }

            await Promise.all([
                this.data?.deeds_available ? this.loadDeeds() : null,
                this.loadProjects(),
                this.loadOffers(),
            ]);
        },

        /**
         * The city the detail is read for: the one chosen, or the one the
         * screen opened on before any quarter of deeds gave it a list to
         * choose from.
         */
        detailCity() {
            return this.city || this.data?.city?.key || '';
        },

        /** The deeds request as a query string, empty filters left out. */
        deedsQuery() {
            const query = new URLSearchParams({ city: this.detailCity() });

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
                const payload = await request('GET', `/api/market-prices/projects?city=${encodeURIComponent(this.detailCity())}`);
                this.projects = payload?.data ?? null;
            } catch (error) {
                this.projectsError = error.message;
            }
        },

        async loadOffers() {
            this.offersError = '';

            try {
                const payload = await request('GET', `/api/market-prices/manual-units?city=${encodeURIComponent(this.detailCity())}`);
                this.offers = payload?.data ?? null;
            } catch (error) {
                this.offersError = error.message;
            }
        },

        /** The kinds a hand-entered unit may be: the market's, or the usual ones before it has been asked. */
        offerKinds() {
            return this.offers?.kinds?.length ? this.offers.kinds : USUAL_KINDS;
        },

        offerError(field) {
            return this.offerErrors?.[field]?.[0] ?? '';
        },

        /** Puts a unit back into the form to be corrected. */
        editOffer(unit) {
            this.editingOffer = unit.id;
            this.offerErrors = {};
            this.offerForm = Object.fromEntries(OFFER_FIELDS.map((field) => [field, unit[field] ?? '']));
            this.offerForm.district = unit.district_key ?? '';
            this.offerForm.observed_on = unit.observed_on ?? today();
            globalThis.scrollTo?.({ top: 0 });
        },

        /**
         * Reads a file of listings collected from listing sites. Listings
         * already entered are updated rather than added twice.
         */
        async importListings(event) {
            const file = event.target.files?.[0];
            event.target.value = '';

            if (!file || this.importingListings) {
                return;
            }

            this.importingListings = true;
            this.listingReport = null;
            this.listingError = '';

            const form = new FormData();
            form.append('file', file);
            form.append('kind', this.listingKind);

            try {
                const payload = await uploadWithProgress('/api/market-prices/manual-units/import', form);
                this.listingReport = payload?.data ?? null;
                await Promise.all([this.loadOffers(), this.loadProjects()]);
            } catch (error) {
                this.listingError = error.errors?.file?.[0] ?? error.errors?.kind?.[0] ?? error.message;
            }

            this.importingListings = false;
        },

        /** What reading a listings file did, in one sentence. */
        listingSummary(report) {
            if (!report) {
                return '';
            }

            const parts = [
                `من هذا الملف: أُضيف ${NUMBER.format(report.added)} إعلانًا وحُدّث ${NUMBER.format(report.updated)}`,
                `وهي ${NUMBER.format(report.properties)} عقارًا مختلفًا`,
            ];

            if (report.repeated) {
                parts.push(`${NUMBER.format(report.repeated)} منها معروض في أكثر من إعلان ويُحسب مرة واحدة`);
            }

            // What the city holds now in all, apart from what this file added to it.
            const totals = (report.totals ?? []).map(
                (total) => `أصبح في ${total.city} ${NUMBER.format(total.listings)} إعلانًا لـ ${NUMBER.format(total.properties)} عقارًا`,
            );

            return [`${parts.join('، ')}.`, ...totals.map((total) => `${total}.`)].join(' ');
        },

        /** A unit's rooms, bathrooms and age, in a few words. */
        offerDetails(unit) {
            const parts = [];

            if (unit.bedrooms ?? unit.rooms) {
                parts.push(`${NUMBER.format(unit.bedrooms ?? unit.rooms)} غرف`);
            }

            if (unit.bathrooms) {
                parts.push(`${NUMBER.format(unit.bathrooms)} دورات مياه`);
            }

            if (unit.property_age_years !== null && unit.property_age_years !== undefined) {
                parts.push(unit.property_age_years === 0 ? 'جديد' : `عمره ${NUMBER.format(unit.property_age_years)} سنة`);
            } else if (unit.property_age) {
                parts.push(unit.property_age);
            }

            return parts.join(' · ');
        },

        /** The street and facade, in a few words. */
        offerStreet(unit) {
            return [unit.street_width ? `شارع ${unit.street_width} م` : '', unit.facade ?? ''].filter(Boolean).join(' · ');
        },

        cancelOfferEdit() {
            this.editingOffer = null;
            this.offerErrors = {};
            this.offerForm = emptyOfferForm();
        },

        /**
         * Adds the unit in the form, or saves the one being corrected. The
         * company's units are read again afterwards: what they are set beside
         * has just changed.
         */
        async saveOffer() {
            if (this.savingOffer) {
                return;
            }

            this.savingOffer = true;
            this.offersError = '';
            this.offerErrors = {};

            const body = { ...this.offerForm, city: this.detailCity() };
            const url = '/api/market-prices/manual-units';

            try {
                await (this.editingOffer
                    ? request('PUT', `${url}/${this.editingOffer}`, body)
                    : request('POST', url, body));

                // The next unit is usually seen in the same place on the same day.
                const { district, kind, observed_on } = this.offerForm;
                this.cancelOfferEdit();
                this.offerForm = { ...this.offerForm, district, kind, observed_on };

                await Promise.all([this.loadOffers(), this.loadProjects()]);
            } catch (error) {
                this.offerErrors = error.errors ?? {};
                this.offersError = error.errors ? '' : error.message;
            }

            this.savingOffer = false;
        },

        async removeOffer(unit) {
            if (!confirm(`حذف «${unit.label}»؟`)) {
                return;
            }

            this.offersError = '';

            try {
                await request('DELETE', `/api/market-prices/manual-units/${unit.id}`);

                if (this.editingOffer === unit.id) {
                    this.cancelOfferEdit();
                }

                await Promise.all([this.loadOffers(), this.loadProjects()]);
            } catch (error) {
                this.offersError = error.message;
            }
        },

        /** «٥ صفقة · حي الصفا» under the deeds, «٥ وحدة · حي الصفا» under the offers. */
        countCaption(figure, project, noun) {
            if (!figure) {
                return '';
            }

            return `${NUMBER.format(figure.deals)} ${noun} · ${this.scopeNote(figure, project)}`;
        },

        /** Places a project in a district, or takes it out of one. */
        async placeProject(project, district) {
            this.placing = project.id;
            this.projectsError = '';

            try {
                const payload = await request('PUT', `/api/market-prices/projects/${project.id}`, {
                    city: this.detailCity(),
                    district: district || null,
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
                    city: this.detailCity(),
                    kind: kind || null,
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
         * Chooses what the «الفرق» column measures against, and keeps the
         * choice on this browser for the next visit. Nothing is read again:
         * every reference is already on the page.
         */
        setGapReference(reference) {
            if (!Object.hasOwn(GAP_REFERENCES, reference)) {
                return;
            }

            this.gapReference = reference;
            save('market.gapReference', reference);
        },

        /** Reads offers of about the unit's own size, or of every size; kept on this browser. */
        setOffersBySize(bySize) {
            this.offersBySize = Boolean(bySize);
            save('market.offersBySize', this.offersBySize);
        },

        /** The offers a unit is set beside, as the size option reads them. */
        unitOffers(unit) {
            return (this.offersBySize ? unit.market?.offers : unit.market?.offers_all) ?? null;
        },

        /**
         * Under the offers figure: how many properties it is out of how many
         * of the kind were entered there, and the sizes it was read between.
         */
        offersCaption(unit, project) {
            const figure = this.unitOffers(unit);

            if (!figure) {
                return '';
            }

            const count = figure.total && figure.total !== figure.deals
                ? `${NUMBER.format(figure.deals)} من ${NUMBER.format(figure.total)} وحدة`
                : `${NUMBER.format(figure.deals)} وحدة`;
            const sizes = figure.area_from
                ? `${NUMBER.format(figure.area_from)}–${NUMBER.format(figure.area_to)} م²`
                : 'كل المساحات';

            return `${count} · ${sizes} · ${this.scopeNote(figure, project)}`;
        },

        /** The reference the «الفرق» column measures against, in words. */
        gapLabel() {
            return GAP_REFERENCES[this.gapReference]?.label ?? '';
        },

        /**
         * How far a unit's price per metre sits from the chosen reference, in
         * percent. Nothing where the unit has no price, or the reference has
         * nothing to say about it, or says it from too few sales or offers to
         * be a market.
         */
        gap(unit) {
            const reference = GAP_REFERENCES[this.gapReference] ?? GAP_REFERENCES.kind;
            const market = this.referenceFigure(unit, reference);
            const value = market?.[reference.value];

            if (!unit.rate || !value || (market.deals ?? 0) < reference.minimum) {
                return null;
            }

            const percent = Math.round(((unit.rate - value) / value) * 100);

            return {
                direction: percent > 0 ? 'above' : percent < 0 ? 'below' : 'level',
                label: percent === 0 ? 'عند السوق' : `${NUMBER.format(Math.abs(percent))}٪ ${percent > 0 ? 'فوق' : 'تحت'} السوق`,
            };
        },

        /** The figure a reference reads off a unit; for offers, as the size option says. */
        referenceFigure(unit, reference) {
            return reference.figure === 'offers' ? this.unitOffers(unit) : unit.market?.[reference.figure];
        },

        /**
         * Why a unit has no gap against the chosen reference, in a few words,
         * so an empty cell is not mistaken for «level with the market».
         */
        gapMissing(unit) {
            const reference = GAP_REFERENCES[this.gapReference] ?? GAP_REFERENCES.kind;
            const market = this.referenceFigure(unit, reference);

            if (!unit.rate) {
                return 'لا سعر أو مساحة';
            }

            if (!market?.[reference.value]) {
                return 'لا مرجع';
            }

            return `أقل من ${NUMBER.format(reference.minimum)} للمقارنة`;
        },
    };
}
