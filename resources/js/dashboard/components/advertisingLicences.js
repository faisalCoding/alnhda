import { crudPage } from './simpleCrud';
import { request } from '../api';

export default function advertisingLicencesPage() {
    return {
        ...crudPage('/api/advertising-licences', {
            id: null,
            properties_id: '',
            unit_name: '',
            licence_number: '',
            expires_on: '',
            note: '',
        }),

        units: [],
        // A unit can be chosen from the list or typed, so the form tracks which.
        manualUnit: false,

        async init() {
            await Promise.all([this.load(), this.loadUnits()]);
            this.loading = false;
        },

        async loadUnits() {
            try {
                this.units = (await request('GET', '/api/properties'))?.data ?? [];
            } catch {
                this.units = [];
            }
        },

        openCreate() {
            this.form = { id: null, properties_id: '', unit_name: '', licence_number: '', expires_on: '', note: '' };
            this.formErrors = {};
            this.manualUnit = this.units.length === 0;
            this.showForm = true;
        },

        openEdit(record) {
            this.form = {
                id: record.id,
                properties_id: record.properties_id ?? '',
                unit_name: record.unit_name ?? '',
                licence_number: record.licence_number,
                expires_on: record.expires_on ?? '',
                note: record.note ?? '',
            };
            this.formErrors = {};
            this.manualUnit = !record.properties_id;
            this.showForm = true;
        },

        /** Only one of the two ways of naming a unit is ever sent. */
        async save() {
            this.saving = true;
            this.formErrors = {};

            const body = {
                licence_number: this.form.licence_number,
                expires_on: this.form.expires_on === '' ? null : this.form.expires_on,
                note: this.form.note === '' ? null : this.form.note,
                properties_id: this.manualUnit || this.form.properties_id === '' ? null : this.form.properties_id,
                unit_name: this.manualUnit ? this.form.unit_name : null,
            };

            try {
                const payload = this.form.id
                    ? await request('PUT', `/api/advertising-licences/${this.form.id}`, body)
                    : await request('POST', '/api/advertising-licences', body);

                const saved = payload.data;
                this.records = this.form.id
                    ? this.records.map((item) => (item.id === saved.id ? saved : item))
                    : [...this.records, saved];

                this.showForm = false;
            } catch (error) {
                this.formErrors = error.errors ?? {};
                // Field messages already say it; the banner would only add Laravel's English tail.
                this.error = Object.keys(this.formErrors).length ? '' : error.message;
            } finally {
                this.saving = false;
            }
        },

        expiryTone(record) {
            const days = record.days_until_expiry;

            if (days === null) {
                return 'text-zinc-400';
            }

            if (days < 0) {
                return 'font-bold text-red-600 dark:text-red-400';
            }

            if (days <= 30) {
                return 'font-bold text-amber-600 dark:text-amber-400';
            }

            return 'text-zinc-600 dark:text-zinc-300';
        },

        expiryLabel(record) {
            const days = record.days_until_expiry;

            if (days === null) {
                return 'بلا تاريخ';
            }

            if (days < 0) {
                return `منتهية منذ ${Math.abs(days)} يوم`;
            }

            if (days === 0) {
                return 'تنتهي اليوم';
            }

            return `${days} يوم متبق`;
        },

        /** Soonest to lapse first, undated last, matching the server's order. */
        get visible() {
            return this.records
                .filter((record) => this.matches(record, record.unit_label, record.licence_number, record.note))
                .sort((a, b) => (a.expires_on ?? '9999').localeCompare(b.expires_on ?? '9999'));
        },

        /** Licences that have lapsed or are close to it, which is what to act on. */
        get needingAttention() {
            return this.records.filter((record) => record.days_until_expiry !== null && record.days_until_expiry <= 30).length;
        },
    };
}
