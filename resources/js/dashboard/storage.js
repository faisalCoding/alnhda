const PREFIX = 'alnhda.panel.v1.';

export function load(key, fallback = null) {
    try {
        const raw = localStorage.getItem(PREFIX + key);

        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

export function save(key, value) {
    try {
        localStorage.setItem(PREFIX + key, JSON.stringify(value));

        return true;
    } catch (error) {
        console.warn('[alnhda-panel] localStorage write failed:', error);

        return false;
    }
}

export function remove(key) {
    try {
        localStorage.removeItem(PREFIX + key);
    } catch {
        // ignore
    }
}
