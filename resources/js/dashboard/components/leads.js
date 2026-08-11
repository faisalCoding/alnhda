import { request } from '../api';
import { downloadCsv, parseCsv, toCsv } from '../csv';
import { isTemp } from '../ids';

const COLUMNS = [
    { key: 'name', label: 'الاسم', aliases: ['name', 'full name', 'العميل', 'اسم العميل'] },
    { key: 'phone', label: 'رقم الهاتف', aliases: ['phone', 'mobile', 'الجوال', 'رقم الجوال', 'الهاتف', 'رقم'] },
    { key: 'property', label: 'العقار', aliases: ['property', 'unit', 'الوحدة', 'العقار المطلوب', 'نوع العقار'] },
    { key: 'lead_date', label: 'التاريخ', aliases: ['date', 'lead date', 'created at', 'تاريخ', 'تاريخ التسجيل'] },
    {
        key: 'classification',
        label: 'التصنيف',
        aliases: ['classification', 'category', 'status', 'type', 'تصنيف', 'الحالة', 'الفئة'],
    },
];

const MAX_IMPORT_ROWS = 2000;

function normalizeHeader(value) {
    return value.trim().toLowerCase().replace(/\s+/g, ' ');
}

/**
 * Accept the common date shapes a spreadsheet produces and return an ISO date,
 * or null when the cell is empty or unparseable.
 */
function normalizeDate(value) {
    const text = (value ?? '').trim();

    if (!text) {
        return null;
    }

    const iso = text.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);

    if (iso) {
        return `${iso[1]}-${iso[2].padStart(2, '0')}-${iso[3].padStart(2, '0')}`;
    }

    const dmy = text.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})$/);

    if (dmy) {
        return `${dmy[3]}-${dmy[2].padStart(2, '0')}-${dmy[1].padStart(2, '0')}`;
    }

    return null;
}

function today() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function emptyForm() {
    return { name: '', phone: '', property: '', lead_date: today(), classification: '' };
}

export default function leadsPage() {
    return {
        panel: false,
        editingId: null,
        form: emptyForm(),
        errors: {},
        search: '',
        classification: '',
        importPreview: null,
        importing: false,
        compose: null,
        composeText: '',
        sending: false,
        sendResult: null,

        init() {
            this.refresh();
        },

        async refresh() {
            if (!navigator.onLine) {
                return;
            }

            try {
                await this.$store.data.revalidate('leads');
            } catch {
                // keep cached data
            }
        },

        isTemp,

        get leads() {
            const term = this.search.trim();

            return this.$store.data.leads.filter((lead) => {
                if (this.classification && (lead.classification ?? '') !== this.classification) {
                    return false;
                }

                if (!term) {
                    return true;
                }

                return `${lead.name ?? ''} ${lead.phone ?? ''} ${lead.property ?? ''}`.includes(term);
            });
        },

        /** Classification values are free text, so the filter is built from the data itself. */
        get classifications() {
            const values = new Set();

            for (const lead of this.$store.data.leads) {
                if (lead.classification) {
                    values.add(lead.classification);
                }
            }

            return Array.from(values).sort();
        },

        formatDate(value) {
            if (!value) {
                return '—';
            }

            const date = new Date(value);

            return Number.isNaN(date.getTime())
                ? value
                : date.toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' });
        },

        openCreate() {
            this.panel = true;
            this.editingId = null;
            this.errors = {};
            this.form = emptyForm();
        },

        openEdit(lead) {
            this.panel = true;
            this.editingId = lead.id;
            this.errors = {};
            this.form = {
                name: lead.name ?? '',
                phone: lead.phone ?? '',
                property: lead.property ?? '',
                lead_date: lead.lead_date ?? '',
                classification: lead.classification ?? '',
            };
        },

        closePanel() {
            this.panel = false;
            this.editingId = null;
            this.errors = {};
        },

        save() {
            this.errors = {};

            if (!this.form.name.trim()) {
                this.errors.name = 'اسم العميل مطلوب.';
            }

            if (!this.form.phone.trim()) {
                this.errors.phone = 'رقم الهاتف مطلوب.';
            }

            if (Object.keys(this.errors).length) {
                return;
            }

            const attributes = {
                name: this.form.name.trim(),
                phone: this.form.phone.trim(),
                property: this.form.property.trim() || null,
                lead_date: this.form.lead_date || null,
                classification: this.form.classification.trim() || null,
            };

            if (this.editingId) {
                this.$store.data.updateRecord('leads', this.editingId, attributes);
            } else {
                this.$store.data.createRecord('leads', attributes);
            }

            this.closePanel();
        },

        remove(lead) {
            if (confirm(`هل أنت متأكد من حذف العميل «${lead.name}»؟`)) {
                this.$store.data.deleteRecord('leads', lead.id);
            }
        },

        /**
         * Read the file and build a preview. Nothing is queued until the admin
         * confirms, so a mis-mapped file costs nothing.
         */
        async previewImport(event) {
            const file = event.target.files[0];

            event.target.value = '';

            if (!file) {
                return;
            }

            this.importing = true;

            try {
                const rows = parseCsv(await file.text());

                this.importPreview = this.buildPreview(file.name, rows);
            } catch {
                this.importPreview = { fileName: file.name, error: 'تعذر قراءة الملف — تأكد أنه ملف CSV سليم.' };
            } finally {
                this.importing = false;
            }
        },

        buildPreview(fileName, rows) {
            if (!rows.length) {
                return { fileName, error: 'الملف فارغ.' };
            }

            const headers = rows[0].map(normalizeHeader);
            const mapping = {};

            for (const column of COLUMNS) {
                const index = headers.findIndex(
                    (header) => header === normalizeHeader(column.label) || column.aliases.includes(header)
                );

                if (index !== -1) {
                    mapping[column.key] = index;
                }
            }

            if (mapping.name === undefined || mapping.phone === undefined) {
                return {
                    fileName,
                    error: 'لم يتم العثور على عمودي «الاسم» و«رقم الهاتف» في الصف الأول من الملف.',
                };
            }

            const cell = (row, key) => (mapping[key] === undefined ? '' : (row[mapping[key]] ?? '').trim());
            const valid = [];
            let skipped = 0;

            for (const row of rows.slice(1)) {
                const name = cell(row, 'name');
                const phone = cell(row, 'phone');

                if (!name || !phone) {
                    skipped++;

                    continue;
                }

                valid.push({
                    name,
                    phone,
                    property: cell(row, 'property') || null,
                    lead_date: normalizeDate(cell(row, 'lead_date')),
                    classification: cell(row, 'classification') || null,
                });
            }

            const truncated = Math.max(0, valid.length - MAX_IMPORT_ROWS);

            return {
                fileName,
                error: null,
                rows: valid.slice(0, MAX_IMPORT_ROWS),
                sample: valid.slice(0, 5),
                skipped,
                truncated,
                columns: COLUMNS.filter((column) => mapping[column.key] !== undefined).map((column) => column.label),
            };
        },

        /**
         * Queue every row through the normal outbox, so an import behaves like a
         * batch of manual additions: optimistic locally, synced when online.
         */
        confirmImport() {
            for (const attributes of this.importPreview.rows) {
                this.$store.data.createRecord('leads', attributes);
            }

            this.importPreview = null;
        },

        cancelImport() {
            this.importPreview = null;
        },

        exportCsv() {
            const leads = this.leads;

            if (!leads.length) {
                alert('لا توجد سجلات للتصدير.');

                return;
            }

            const rows = leads.map((lead) => [
                lead.name ?? '',
                lead.phone ?? '',
                lead.property ?? '',
                lead.lead_date ?? '',
                lead.classification ?? '',
            ]);

            downloadCsv(
                `leads-${today()}.csv`,
                toCsv(
                    COLUMNS.map((column) => column.label),
                    rows
                )
            );
        },

        /**
         * WhatsApp goes straight to the server (and the local gateway), so unlike
         * the CRUD actions it needs a live connection and a synced record.
         */
        canSendTo(lead) {
            return !isTemp(lead.id) && this.$store.sync.online;
        },

        get sendableLeads() {
            return this.leads.filter((lead) => this.canSendTo(lead));
        },

        openSend(lead) {
            this.compose = { targets: [lead], bulk: false };
            this.composeText = '';
            this.sendResult = null;
        },

        openBulkSend() {
            const targets = this.sendableLeads;

            if (!targets.length) {
                alert('لا يوجد عملاء جاهزون للإرسال — تأكد من الاتصال ومن اكتمال المزامنة.');

                return;
            }

            this.compose = { targets, bulk: true };
            this.composeText = '';
            this.sendResult = null;
        },

        closeSend() {
            this.compose = null;
            this.composeText = '';
            this.sendResult = null;
        },

        async submitSend() {
            if (!this.composeText.trim() || this.sending) {
                return;
            }

            this.sending = true;
            this.sendResult = null;

            try {
                const payload = await request('POST', '/api/whatsapp/send', {
                    message: this.composeText,
                    lead_ids: this.compose.targets.map((lead) => lead.id),
                });

                this.sendResult = { ok: true, ...payload.data };
            } catch (error) {
                this.sendResult = { ok: false, message: error.message };
            } finally {
                this.sending = false;
            }
        },

        downloadTemplate() {
            downloadCsv(
                'leads-template.csv',
                toCsv(
                    COLUMNS.map((column) => column.label),
                    [['محمد العتيبي', '0555555555', 'فيلا', today(), 'مهتم']]
                )
            );
        },
    };
}
