import { ApiError, request } from '../api';
import { uuid } from '../ids';

const BASE = '/api/social-platforms';

/**
 * This page deliberately talks to the API directly instead of going through the
 * offline data store: that store mirrors every record and every queued payload
 * into localStorage, which is no place for account passwords.
 */
export default function socialAccountsPage() {
    return {
        platforms: [],
        templates: [],
        loading: true,
        saving: false,
        error: '',

        form: { id: null, name: '', identifier: '', password: '' },
        formErrors: {},
        showForm: false,

        templateForm: '',
        showTemplates: false,

        newTask: {},

        pinIsSet: false,
        pinPrompt: { open: false, platformId: null, value: '', error: '', busy: false },
        pinSetup: { open: false, pin: '', pin_confirmation: '', current_password: '', error: '', busy: false },

        // Revealed passwords live here only, never in the store or localStorage.
        revealed: {},
        revealTimers: {},

        async init() {
            await Promise.all([this.loadPlatforms(), this.loadTemplates(), this.loadPinState()]);
            this.loading = false;
        },

        async loadPlatforms() {
            try {
                const payload = await request('GET', BASE);
                this.platforms = payload?.data ?? [];
            } catch (error) {
                this.error = error.message;
            }
        },

        async loadTemplates() {
            try {
                const payload = await request('GET', '/api/task-templates');
                this.templates = payload?.data ?? [];
            } catch {
                // the page still works without the template list
            }
        },

        async loadPinState() {
            try {
                const payload = await request('GET', '/api/reveal-pin');
                this.pinIsSet = payload?.data?.is_set ?? false;
            } catch {
                this.pinIsSet = false;
            }
        },

        // ---- platforms ------------------------------------------------------

        openCreate() {
            this.form = { id: null, name: '', identifier: '', password: '' };
            this.formErrors = {};
            this.showForm = true;
        },

        openEdit(platform) {
            this.form = { id: platform.id, name: platform.name, identifier: platform.identifier, password: '' };
            this.formErrors = {};
            this.showForm = true;
        },

        async savePlatform() {
            this.saving = true;
            this.formErrors = {};

            const isUpdate = this.form.id !== null;
            const body = { name: this.form.name, identifier: this.form.identifier };

            // An untouched password field on edit must not wipe the stored one.
            if (this.form.password !== '' || !isUpdate) {
                body.password = this.form.password;
            }

            try {
                const payload = isUpdate
                    ? await request('PUT', `${BASE}/${this.form.id}`, body, { idempotencyKey: uuid() })
                    : await request('POST', BASE, body, { idempotencyKey: uuid() });

                const saved = payload.data;

                if (isUpdate) {
                    this.platforms = this.platforms.map((item) => (item.id === saved.id ? saved : item));
                } else {
                    this.platforms = [...this.platforms, saved];
                }

                this.showForm = false;
                this.form.password = '';
            } catch (error) {
                this.formErrors = error instanceof ApiError ? error.errors : {};
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        },

        async deletePlatform(platform) {
            if (!confirm(`حذف «${platform.name}» وكل مهامه؟`)) {
                return;
            }

            try {
                await request('DELETE', `${BASE}/${platform.id}`, null, { idempotencyKey: uuid() });
                this.platforms = this.platforms.filter((item) => item.id !== platform.id);
                this.hide(platform.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- reveal ---------------------------------------------------------

        askForPin(platform) {
            if (!this.pinIsSet) {
                this.pinSetup.open = true;

                return;
            }

            this.pinPrompt = { open: true, platformId: platform.id, value: '', error: '', busy: false };
        },

        async submitPin() {
            this.pinPrompt.busy = true;
            this.pinPrompt.error = '';

            try {
                const payload = await request('POST', `${BASE}/${this.pinPrompt.platformId}/reveal`, {
                    pin: this.pinPrompt.value,
                });

                this.revealed[this.pinPrompt.platformId] = payload.data.secret;
                this.scheduleHide(this.pinPrompt.platformId);
                this.pinPrompt.open = false;
            } catch (error) {
                this.pinPrompt.error = error.errors?.pin?.[0] ?? error.message;
            } finally {
                this.pinPrompt.busy = false;
            }
        },

        /** Revealed passwords clear themselves so they do not linger on screen. */
        scheduleHide(platformId) {
            clearTimeout(this.revealTimers[platformId]);
            this.revealTimers[platformId] = setTimeout(() => this.hide(platformId), 60000);
        },

        hide(platformId) {
            clearTimeout(this.revealTimers[platformId]);
            delete this.revealTimers[platformId];
            delete this.revealed[platformId];
        },

        async copy(platformId) {
            const value = this.revealed[platformId];

            if (!value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
            } catch {
                this.error = 'تعذر النسخ إلى الحافظة';
            }
        },

        async saveRevealPin() {
            this.pinSetup.busy = true;
            this.pinSetup.error = '';

            try {
                await request('PUT', '/api/reveal-pin', {
                    pin: this.pinSetup.pin,
                    pin_confirmation: this.pinSetup.pin_confirmation,
                    current_password: this.pinSetup.current_password,
                });

                this.pinIsSet = true;
                this.pinSetup = { open: false, pin: '', pin_confirmation: '', current_password: '', error: '', busy: false };
            } catch (error) {
                this.pinSetup.error = error.errors?.pin?.[0] ?? error.errors?.current_password?.[0] ?? error.message;
            } finally {
                this.pinSetup.busy = false;
            }
        },

        // ---- tasks ----------------------------------------------------------

        async toggleTask(platform, task) {
            const next = !task.is_done;
            task.is_done = next;

            try {
                await request('PUT', `/api/social-platform-tasks/${task.id}`, { is_done: next }, { idempotencyKey: uuid() });
            } catch (error) {
                task.is_done = !next;
                this.error = error.message;
            }
        },

        async addTask(platform) {
            const title = (this.newTask[platform.id] ?? '').trim();

            if (title === '') {
                return;
            }

            try {
                const payload = await request('POST', `${BASE}/${platform.id}/tasks`, { title }, { idempotencyKey: uuid() });
                platform.tasks = [...platform.tasks, payload.data];
                this.newTask[platform.id] = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        async deleteTask(platform, task) {
            try {
                await request('DELETE', `/api/social-platform-tasks/${task.id}`, null, { idempotencyKey: uuid() });
                platform.tasks = platform.tasks.filter((item) => item.id !== task.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        async applyTemplates(platform) {
            try {
                const payload = await request('POST', `${BASE}/${platform.id}/apply-templates`, {}, { idempotencyKey: uuid() });
                this.platforms = this.platforms.map((item) => (item.id === payload.data.id ? payload.data : item));
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- templates ------------------------------------------------------

        async addTemplate() {
            const title = this.templateForm.trim();

            if (title === '') {
                return;
            }

            try {
                const payload = await request('POST', '/api/task-templates', { title }, { idempotencyKey: uuid() });
                this.templates = [...this.templates, payload.data];
                this.templateForm = '';
            } catch (error) {
                this.error = error.message;
            }
        },

        async deleteTemplate(template) {
            try {
                await request('DELETE', `/api/task-templates/${template.id}`, null, { idempotencyKey: uuid() });
                this.templates = this.templates.filter((item) => item.id !== template.id);
            } catch (error) {
                this.error = error.message;
            }
        },

        // ---- derived --------------------------------------------------------

        progress(platform) {
            const total = platform.tasks?.length ?? 0;

            if (total === 0) {
                return { done: 0, total: 0, percent: 0 };
            }

            const done = platform.tasks.filter((task) => task.is_done).length;

            return { done, total, percent: Math.round((done / total) * 100) };
        },
    };
}
