import { request } from '../api.js';

const NUMBER = new Intl.NumberFormat('ar-SA');

/** How many candidates the picker lists at once; a search narrows the rest. */
export const CANDIDATE_LIMIT = 30;

/**
 * The competitors group of the market prices screen: under each of the
 * company's units, the market units chosen by hand as its competitors, with
 * their details and how far the unit's price sits from theirs.
 *
 * Competitors are chosen from the hand-entered market units of the city,
 * which the offers group loads; nothing here is typed in twice.
 *
 * Spread into the page component, so everything here is a plain property or a
 * method: a getter would be read once, at the spread, and frozen.
 */
export default function marketCompetitors() {
    return {
        competitors: null,
        competitorsError: '',

        /** The unit whose picker is open, and what the picker is narrowed by. */
        pickingFor: null,
        pickSearch: '',
        pickSameDistrict: true,
        pickSameKind: true,
        pickNote: '',
        savingCompetitor: false,

        async loadCompetitors() {
            this.competitorsError = '';

            try {
                const city = typeof this.detailCity === 'function' ? this.detailCity() : this.city;
                const payload = await request('GET', `/api/market-prices/competitors?city=${encodeURIComponent(city ?? '')}`);
                this.competitors = payload?.data ?? null;
            } catch (error) {
                this.competitorsError = error.message;
            }
        },

        /** Opens the picker under one unit, or closes it when it is already open. */
        togglePicker(unit) {
            this.pickingFor = this.pickingFor === unit.id ? null : unit.id;
            this.pickSearch = '';
            this.pickNote = '';
        },

        /**
         * The district a project is read in on the units report: the one set
         * by hand, or the one its location names.
         */
        projectDistrict(projectId) {
            const project = (this.projects?.projects ?? []).find((item) => item.id === projectId);

            return project?.district ?? project?.guessed_district ?? null;
        },

        /**
         * The market units a unit may be given as competitors: not already
         * chosen, narrowed to its district and kind unless told otherwise,
         * and the nearest in size first.
         */
        candidates(unit, project) {
            const chosen = new Set((unit.competitors ?? []).map((competitor) => competitor.id));
            const district = this.projectDistrict(project.id);
            const term = this.pickSearch.trim();
            const size = unit.basis === 'land' ? unit.land_area : unit.area;

            return (this.offers?.units ?? [])
                .filter((offer) => !chosen.has(offer.id))
                .filter((offer) => !this.pickSameKind || !unit.kind || offer.kind === unit.kind)
                .filter((offer) => !this.pickSameDistrict || !district || offer.district_key === district)
                .filter((offer) => !term
                    || [offer.label, offer.project_name, offer.district, offer.listing_id].some((field) => String(field ?? '').includes(term)))
                .sort((a, b) => (size ? Math.abs(a.area - size) - Math.abs(b.area - size) : b.area - a.area))
                .slice(0, CANDIDATE_LIMIT);
        },

        /** Chooses a market unit as a competitor, or keeps a new note on one already chosen. */
        async addCompetitor(unit, offer, note = null) {
            if (this.savingCompetitor) {
                return;
            }

            this.savingCompetitor = true;
            this.competitorsError = '';

            try {
                await request('POST', `/api/market-prices/units/${unit.id}/competitors`, {
                    manual_market_unit_id: offer.id,
                    note: note ?? (this.pickNote.trim() || null),
                });
                this.pickNote = '';
                await this.loadCompetitors();
            } catch (error) {
                this.competitorsError = error.errors?.manual_market_unit_id?.[0] ?? error.errors?.note?.[0] ?? error.message;
            }

            this.savingCompetitor = false;
        },

        /** Changes the note kept on a competitor: why it was chosen, what sets it apart. */
        async editCompetitorNote(unit, competitor) {
            const note = prompt(`ملاحظة على «${competitor.label}»`, competitor.note ?? '');

            if (note === null) {
                return;
            }

            await this.addCompetitor(unit, competitor, note.trim());
        },

        async removeCompetitor(unit, competitor) {
            if (!confirm(`إزالة «${competitor.label}» من منافسي «${unit.name}»؟`)) {
                return;
            }

            this.competitorsError = '';

            try {
                await request('DELETE', `/api/market-prices/units/${unit.id}/competitors/${competitor.id}`);
                await this.loadCompetitors();
            } catch (error) {
                this.competitorsError = error.message;
            }
        },

        /** How many of the company's units have competitors, and how many links in all. */
        competitorCounts() {
            const units = (this.competitors?.projects ?? []).flatMap((project) => project.units);

            return {
                units: units.filter((unit) => unit.competitors.length).length,
                links: units.reduce((sum, unit) => sum + unit.competitors.length, 0),
            };
        },

        /**
         * Where the unit's price per metre sits against a competitor's, from
         * the unit's side: «أعلى ١٢٪» means the unit asks twelve percent more.
         */
        competitorGap(gap) {
            if (gap === null || gap === undefined) {
                return { direction: null, label: '—' };
            }

            if (gap === 0) {
                return { direction: 'level', label: 'مساوٍ' };
            }

            return {
                direction: gap > 0 ? 'above' : 'below',
                label: `${gap > 0 ? 'أعلى' : 'أقل'} ${NUMBER.format(Math.abs(gap))}٪`,
            };
        },
    };
}
