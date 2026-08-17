/**
 * Client-side mirrors of the server's format rules.
 *
 * These are not a convenience duplicate of the backend. The panel is
 * offline-first: a record created without a connection is accepted locally and
 * only reaches the server later, so any field the server validates and the
 * browser does not becomes a queued operation that fails on sync and can never
 * be retried — the same payload is resent every time. Validating here is what
 * keeps an unsendable operation out of the outbox in the first place.
 */

/**
 * Matches Laravel's `url` rule for a link a user is expected to paste: an
 * absolute address with a scheme, not a bare domain. Restricted to http(s)
 * because every field using this holds a web link.
 */
export function isAbsoluteUrl(value) {
    try {
        const { protocol } = new URL(value);

        return protocol === 'http:' || protocol === 'https:';
    } catch {
        return false;
    }
}
