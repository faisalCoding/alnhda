export function uuid() {
    if (window.crypto?.randomUUID) {
        return crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;

        return v.toString(16);
    });
}

export function tempId() {
    return 'tmp_' + uuid();
}

export function isTemp(id) {
    return typeof id === 'string' && id.startsWith('tmp_');
}
