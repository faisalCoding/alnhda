/**
 * What the project and unit forms share with the market figures: a location
 * and a kind of property written in the market's own words, so the company's
 * records can be set beside them without matching names by hand.
 */

/** The kinds offered when the panel has not been able to ask for the market's. */
export const USUAL_KINDS = ['شقة', 'فيلا', 'دور', 'دوبلكس', 'أرض', 'عمارة'];

/** The label a list of `{ key, label }` choices gives a key, or nothing. */
export function labelOf(choices, key) {
    return (choices ?? []).find((choice) => choice.key === key)?.label ?? '';
}

/**
 * A location as a buyer reads it, written from a city and a district:
 * «جدة حي الصفا». The server writes the same when it saves; this is what the
 * list shows until it has.
 */
export function composeLocation(city, district) {
    if (!city) {
        return '';
    }

    if (!district) {
        return city;
    }

    return `${city} ${district.startsWith('حي ') ? district : `حي ${district}`}`;
}

/** A name as two spellings of it are compared: hamzas, taa marbuta and spacing folded. */
function loose(name) {
    return String(name ?? '')
        .replace(/[\u200e\u200f]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/\s+/g, '')
        .replace(/ء$/, '');
}

/**
 * The city and the district a typed location names, out of the market's own
 * lists: «جدة حي المنار» is جدة and المنار. A location that names no city of
 * the list, or no «حي», names nothing.
 *
 * @returns {{ city: string, district: string }} Keys, empty where not found.
 */
export function placeIn(location, cities, districts = []) {
    const text = String(location ?? '').replace(/[\u200e\u200f]/g, '').trim();
    const city = (cities ?? []).find((item) => loose(text).startsWith(loose(item.label)));
    const named = text.match(/حي\s*(.+)$/)?.[1];
    const district = named ? (districts ?? []).find((item) => loose(item.label) === loose(named)) : null;

    return { city: city?.key ?? '', district: district?.key ?? '' };
}

/**
 * The kinds a unit may be, with the one it already is kept on the list even
 * when the market does not price it: an old unit typed «ملحق» must open as
 * «ملحق», not silently become the first choice.
 */
export function kindChoices(kinds, current) {
    const choices = kinds?.length ? [...kinds] : [...USUAL_KINDS];

    if (current && !choices.includes(current)) {
        choices.push(current);
    }

    return choices;
}
