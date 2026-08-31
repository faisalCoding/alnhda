/**
 * The vocabulary the panel uses for one record pointing at another: the button
 * an article carries, and the records a collection page gathers.
 *
 * A picker holds one string — `"project:12"` — because a native select can only
 * carry one value, while the server is sent the type and the id apart. Records
 * still waiting to sync are left out of every list on purpose: their id is a
 * local placeholder the server has never seen, so a link pointing at one would
 * be rejected on sync and wedge the outbox with a payload that can never
 * succeed.
 */
import { isTemp } from './ids.js';

/**
 * Mirrors the `max` rule on the server's `cta_label`.
 */
export const CTA_LABEL_LIMIT = 60;

export const GROUP_LABELS = {
    project: 'المشاريع',
    properties: 'الوحدات',
    article: 'المقالات',
    collection: 'الصفحات المجمّعة',
};

/**
 * What a collection page may gather. A page is left out of its own list: a page
 * whose items are pages leads a reader in circles. Mirrors LinkTargets::ITEM_TYPES.
 */
export const ITEM_KINDS = ['project', 'properties', 'article'];

const SOURCES = {
    project: (data) => data.projects,
    properties: (data) => data.properties,
    article: (data) => data.articles,
    collection: (data) => data.collections,
};

const nameOf = (record) => record.title ?? record.name ?? '';

export function composeValue(type, id) {
    return type && id !== null && id !== undefined && id !== '' ? `${type}:${id}` : '';
}

/**
 * @returns {{cta_target_type: string|null, cta_target_id: number|null}}
 */
export function parseCtaValue(value) {
    const [type, id] = String(value ?? '').split(':');

    if (!type || !id || !(type in GROUP_LABELS)) {
        return { cta_target_type: null, cta_target_id: null };
    }

    return { cta_target_type: type, cta_target_id: Number(id) };
}

/**
 * The destinations offered by a picker, grouped by kind. A group with nothing
 * left to offer is dropped rather than shown empty.
 *
 * @param {{projects: Array, properties: Array, articles: Array, collections: Array}} data
 * @param {{kinds?: string[], excludeArticleId?: number|string|null, exclude?: string[]}} options
 */
export function linkOptions(data, { kinds = Object.keys(GROUP_LABELS), excludeArticleId = null, exclude = [] } = {}) {
    const taken = new Set(exclude);

    return kinds
        .map((type) => ({
            type,
            label: GROUP_LABELS[type],
            options: (SOURCES[type]?.(data) ?? [])
                .filter((record) => !isTemp(record.id))
                .filter((record) => !(type === 'article' && record.id === excludeArticleId))
                .map((record) => ({ value: composeValue(type, record.id), label: nameOf(record) }))
                .filter((option) => !taken.has(option.value)),
        }))
        .filter((entry) => entry.options.length > 0);
}

/**
 * What a saved link points at, read from the records the panel already holds.
 *
 * The server sends the destination's name with each record, but one edited
 * offline has only the type and the id until it syncs — resolving the name
 * locally keeps the screen honest in the meantime.
 *
 * @param {{projects: Array, properties: Array, articles: Array, collections: Array}} data
 */
export function targetName(data, type, id) {
    const record = (SOURCES[type]?.(data) ?? []).find((entry) => entry.id === id);

    return record ? nameOf(record) || null : null;
}

/**
 * Names one `"type:id"` entry for display, falling back to the raw value so a
 * record the panel has not loaded still shows something.
 */
export function labelForValue(data, value) {
    const { cta_target_type: type, cta_target_id: id } = parseCtaValue(value);

    return targetName(data, type, id) ?? value;
}

/**
 * Moves one entry within an ordered list, returning a new list. Out-of-range
 * moves return the list unchanged, so the first item's "up" is a no-op rather
 * than a wrap-around.
 */
export function moveItem(list, index, offset) {
    const target = index + offset;

    if (index < 0 || index >= list.length || target < 0 || target >= list.length) {
        return [...list];
    }

    const next = [...list];
    const [moved] = next.splice(index, 1);
    next.splice(target, 0, moved);

    return next;
}
