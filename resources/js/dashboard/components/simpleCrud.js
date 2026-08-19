import { ApiError, request } from '../api';
import { uuid } from '../ids';

/**
 * Shared behaviour for the flat reference lists in the panel. Like the social
 * accounts page these talk to the API directly rather than through the offline
 * store, so nothing is mirrored into localStorage.
 *
 * @param {string} endpoint  API path, e.g. '/api/useful-links'
 * @param {object} blank     an empty form record
 */
export function crudPage(endpoint, blank) {
    return {
        records: [],
        loading: true,
        saving: false,
        error: '',
        search: '',

        form: { ...blank },
        formErrors: {},
        showForm: false,

        async init() {
            await this.load();
            this.loading = false;
        },

        async load() {
            try {
                const payload = await request('GET', endpoint);
                this.records = payload?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        openCreate() {
            this.form = { ...blank };
            this.formErrors = {};
            this.showForm = true;
        },

        openEdit(record) {
            this.form = { ...blank, ...record };
            this.formErrors = {};
            this.showForm = true;
        },

        async save() {
            this.saving = true;
            this.formErrors = {};

            const isUpdate = Boolean(this.form.id);
            const body = { ...this.form };
            delete body.id;

            try {
                const payload = isUpdate
                    ? await request('PUT', `${endpoint}/${this.form.id}`, body, { idempotencyKey: uuid() })
                    : await request('POST', endpoint, body, { idempotencyKey: uuid() });

                const saved = payload.data;

                this.records = isUpdate
                    ? this.records.map((item) => (item.id === saved.id ? saved : item))
                    : [...this.records, saved];

                this.showForm = false;
            } catch (error) {
                this.formErrors = error instanceof ApiError ? error.errors : {};
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        async remove(record) {
            if (!confirm('هل تريد الحذف؟')) {
                return;
            }

            try {
                await request('DELETE', `${endpoint}/${record.id}`, null, { idempotencyKey: uuid() });
                this.records = this.records.filter((item) => item.id !== record.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        matches(record, ...fields) {
            const term = this.search.trim();

            if (term === '') {
                return true;
            }

            return fields.some((value) => String(value ?? '').includes(term));
        },
    };
}
