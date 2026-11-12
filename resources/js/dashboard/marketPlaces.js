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
