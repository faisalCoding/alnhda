import { ApiError, request } from '../api';
import { uuid } from '../ids';

/**
 * Two halves that feed each other: a library of marketing methods written as
 * plain text, and named checklists built by pulling methods out of that library
 * and ticking them off.
 */
export default function marketingToolsPage() {
    return {
        methods: [],
        checklists: [],
        loading: true,
        error: '',

        methodForm: '',
        editingMethod: null,
        editingMethodTitle: '',

        checklistForm: { name: '', method_ids: [] },
        checklistErrors: {},
        showChecklistForm: false,
        saving: false,

        picker: { open: false, checklistId: null, selected: [] },
        newItem: {},

        async init() {
            await Promise.all([this.loadMethods(), this.loadChecklists()]);
            this.loading = false;
        },

        async loadMethods() {
            try {
                const payload = await request('GET', '/api/marketing-methods');
                this.methods = payload?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        async loadChecklists() {
            try {
                const payload = await request('GET', '/api/marketing-checklists');
                this.checklists = payload?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- methods --------------------------------------------------------

        async addMethod() {
            const title = this.methodForm.trim();

            if (title === '') {
                return;
            }

            try {
                const payload = await request('POST', '/api/marketing-methods', { title }, { idempotencyKey: uuid() });
                this.methods = [...this.methods, payload.data];
                this.methodForm = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        startEditMethod(method) {
            this.editingMethod = method.id;
            this.editingMethodTitle = method.title;
        },

        async saveMethod(method) {
            const title = this.editingMethodTitle.trim();

            if (title === '' || title === method.title) {
                this.editingMethod = null;

                return;
            }

            try {
                const payload = await request('PUT', `/api/marketing-methods/${method.id}`, { title }, { idempotencyKey: uuid() });
                this.methods = this.methods.map((item) => (item.id === method.id ? payload.data : item));
            } catch (error) {
                this.error = error.message;
            } finally {
                this.editingMethod = null;
            }
        },

        async removeMethod(method) {
            if (!confirm(`حذف «${method.title}» من قائمة الطرق؟`)) {
                return;
            }

            try {
                await request('DELETE', `/api/marketing-methods/${method.id}`, null, { idempotencyKey: uuid() });
                this.methods = this.methods.filter((item) => item.id !== method.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- checklists -----------------------------------------------------

        openChecklistForm() {
            this.checklistForm = { name: '', method_ids: [] };
            this.checklistErrors = {};
            this.showChecklistForm = true;
        },

        async createChecklist() {
            this.saving = true;
            this.checklistErrors = {};

            try {
                const payload = await request('POST', '/api/marketing-checklists', this.checklistForm, { idempotencyKey: uuid() });
                this.checklists = [...this.checklists, payload.data];
                this.showChecklistForm = false;
            } catch (error) {
                this.checklistErrors = error instanceof ApiError ? error.errors : {};
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        async renameChecklist(checklist, name) {
            const trimmed = name.trim();

            if (trimmed === '' || trimmed === checklist.name) {
                return;
            }

            try {
                const payload = await request('PUT', `/api/marketing-checklists/${checklist.id}`, { name: trimmed }, { idempotencyKey: uuid() });
                this.checklists = this.checklists.map((item) => (item.id === checklist.id ? payload.data : item));
            } catch (error) {
                this.error = error.message;
            }
        },

        async removeChecklist(checklist) {
            if (!confirm(`حذف قائمة «${checklist.name}» وكل مهامها؟`)) {
                return;
            }

            try {
                await request('DELETE', `/api/marketing-checklists/${checklist.id}`, null, { idempotencyKey: uuid() });
                this.checklists = this.checklists.filter((item) => item.id !== checklist.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- pulling methods into a checklist -------------------------------

        openPicker(checklist) {
            this.picker = { open: true, checklistId: checklist.id, selected: [] };
        },

        toggleSelection(methodId) {
            this.picker.selected = this.picker.selected.includes(methodId)
                ? this.picker.selected.filter((id) => id !== methodId)
                : [...this.picker.selected, methodId];
        },

        async confirmPicker() {
            if (this.picker.selected.length === 0) {
                return;
            }

            try {
                const payload = await request(
                    'POST',
                    `/api/marketing-checklists/${this.picker.checklistId}/methods`,
                    { method_ids: this.picker.selected },
                    { idempotencyKey: uuid() },
                );

                this.checklists = this.checklists.map((item) => (item.id === payload.data.id ? payload.data : item));
                this.picker.open = false;
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- checklist items ------------------------------------------------

        async toggleItem(checklist, item) {
            const next = !item.is_done;
            item.is_done = next;

            try {
                await request('PUT', `/api/marketing-checklist-items/${item.id}`, { is_done: next }, { idempotencyKey: uuid() });
            } catch (error) {
                item.is_done = !next;
                this.error = error.message;
            }
        },

        async addItem(checklist) {
            const title = (this.newItem[checklist.id] ?? '').trim();

            if (title === '') {
                return;
            }

            try {
                const payload = await request('POST', `/api/marketing-checklists/${checklist.id}/items`, { title }, { idempotencyKey: uuid() });
                checklist.items = [...checklist.items, payload.data];
                this.newItem[checklist.id] = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        async removeItem(checklist, item) {
            try {
                await request('DELETE', `/api/marketing-checklist-items/${item.id}`, null, { idempotencyKey: uuid() });
                checklist.items = checklist.items.filter((entry) => entry.id !== item.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        progress(checklist) {
            const total = checklist.items?.length ?? 0;

            if (total === 0) {
                return { done: 0, total: 0, percent: 0 };
            }

            const done = checklist.items.filter((item) => item.is_done).length;

            return { done, total, percent: Math.round((done / total) * 100) };
        },
    };
}
