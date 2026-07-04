import { request } from '../api';
import { isTemp } from '../ids';
import * as storage from '../storage';

const PROJECT_DEFAULTS = () => ({
    name: '',
    description: '',
    location: '',
    status: 'جديد',
    project_type: 'فيلا',
    map_url: '',
});

const PROPERTY_DEFAULTS = () => ({
    name: 'شقة',
    project_id: '',
    price: 550000,
    offer: '',
    status: 'جديد',
    rooms: 5,
    bathrooms: 3,
    living_rooms: 1,
    mainds_room: 1,
    area: 180,
    doors: 2,
    type: 'شقة',
    parkings: 1,
    driver_room: 1,
    facade: 'شرقية جنوبية',
    furniture: true,
    unit_youtube: '',
    stages_building_youtube: '',
});

export default function projectsPage() {
    return {
        activeTab: storage.load('ui.projectsTab', 'projects'),
        panel: null,
        editingId: null,
        form: {},
        guarantees: [],
        errors: {},
        search: '',

        init() {
            this.refresh();
        },

        async refresh() {
            if (!navigator.onLine) {
                return;
            }

            try {
                await Promise.all([
                    this.$store.data.revalidate('projects'),
                    this.$store.data.revalidate('properties'),
                ]);
            } catch {
                // keep cached data
            }
        },

        switchTab(tab) {
            this.activeTab = tab;
            storage.save('ui.projectsTab', tab);
        },

        get projects() {
            const term = this.search.trim();
            const projects = this.$store.data.projects;

            return term ? projects.filter((project) => (project.name ?? '').includes(term)) : projects;
        },

        get properties() {
            const term = this.search.trim();
            const properties = this.$store.data.properties;

            return term ? properties.filter((property) => (property.name ?? '').includes(term)) : properties;
        },

        get projectOptions() {
            return this.$store.data.projects;
        },

        projectName(projectId) {
            return this.$store.data.find('projects', projectId)?.name ?? '—';
        },

        unitsCount(project) {
            return this.$store.data.properties.filter((property) => property.project_id === project.id).length;
        },

        isTemp,

        canUploadFor(record) {
            return !isTemp(record.id) && this.$store.sync.online;
        },

        // ----- project form -----

        openCreateProject() {
            this.panel = 'project';
            this.editingId = null;
            this.errors = {};
            this.form = PROJECT_DEFAULTS();
            this.guarantees = [''];
        },

        openEditProject(record) {
            this.panel = 'project';
            this.editingId = record.id;
            this.errors = {};
            this.form = {
                name: record.name ?? '',
                description: record.description ?? '',
                location: record.location ?? '',
                status: record.status ?? 'جديد',
                project_type: record.project_type ?? 'فيلا',
                map_url: record.map_url ?? '',
            };
            this.guarantees = (record.guarantees ?? []).length ? [...record.guarantees] : [''];
        },

        addGuarantee() {
            this.guarantees.push('');
        },

        removeGuarantee(index) {
            this.guarantees.splice(index, 1);
        },

        saveProject() {
            this.errors = {};

            if ((this.form.name ?? '').trim().length < 3) {
                this.errors.name = 'اسم المشروع يجب أن يكون 3 أحرف على الأقل.';
            }

            if ((this.form.description ?? '').trim().length < 10) {
                this.errors.description = 'وصف المشروع يجب أن يكون 10 أحرف على الأقل.';
            }

            if (Object.keys(this.errors).length) {
                return;
            }

            const attributes = {
                ...this.form,
                map_url: this.form.map_url || null,
                location: this.form.location || null,
                guarantees: this.guarantees.map((guarantee) => guarantee.trim()).filter(Boolean),
            };

            if (this.editingId) {
                this.$store.data.updateRecord('projects', this.editingId, attributes);
            } else {
                this.$store.data.createRecord('projects', attributes);
            }

            this.closePanel();
        },

        deleteProject(record) {
            if (this.unitsCount(record) > 0) {
                alert('لا يمكن حذف مشروع يحتوي على وحدات. احذف وحداته أولًا.');

                return;
            }

            if (confirm(`هل أنت متأكد من حذف المشروع «${record.name}»؟`)) {
                this.$store.data.deleteRecord('projects', record.id);
            }
        },

        // ----- property form -----

        openCreateProperty() {
            this.panel = 'property';
            this.editingId = null;
            this.errors = {};
            this.form = PROPERTY_DEFAULTS();
            this.form.project_id = this.$store.data.projects[0]?.id ?? '';
        },

        openEditProperty(record) {
            this.panel = 'property';
            this.editingId = record.id;
            this.errors = {};
            this.form = {
                name: record.name ?? '',
                project_id: record.project_id ?? '',
                price: record.price ?? '',
                offer: record.offer ?? '',
                status: record.status ?? 'جديد',
                rooms: record.rooms ?? 0,
                bathrooms: record.bathrooms ?? 0,
                living_rooms: record.living_rooms ?? 0,
                mainds_room: record.mainds_room ?? 0,
                area: record.area ?? 0,
                doors: record.doors ?? 0,
                type: record.type ?? 'شقة',
                parkings: record.parkings ?? 0,
                driver_room: record.driver_room ?? 0,
                facade: record.facade ?? '',
                furniture: Boolean(record.furniture),
                unit_youtube: record.unit_youtube ?? '',
                stages_building_youtube: record.stages_building_youtube ?? '',
            };
        },

        saveProperty() {
            this.errors = {};

            if ((this.form.name ?? '').trim().length < 3) {
                this.errors.name = 'اسم الوحدة يجب أن يكون 3 أحرف على الأقل.';
            }

            if (!this.form.project_id) {
                this.errors.project_id = 'يجب اختيار المشروع التابع للوحدة.';
            }

            if (this.form.price === '' || this.form.price === null || isNaN(Number(this.form.price))) {
                this.errors.price = 'سعر الوحدة مطلوب ويجب أن يكون رقمًا.';
            }

            if (Object.keys(this.errors).length) {
                return;
            }

            const attributes = {
                ...this.form,
                price: Number(this.form.price),
                offer: this.form.offer === '' || this.form.offer === null ? null : Number(this.form.offer),
                furniture: Boolean(this.form.furniture),
                unit_youtube: this.form.unit_youtube || null,
                stages_building_youtube: this.form.stages_building_youtube || null,
            };

            if (this.editingId) {
                this.$store.data.updateRecord('properties', this.editingId, attributes);
            } else {
                this.$store.data.createRecord('properties', attributes);
            }

            this.closePanel();
        },

        deleteProperty(record) {
            if (confirm(`هل أنت متأكد من حذف الوحدة «${record.name}»؟`)) {
                this.$store.data.deleteRecord('properties', record.id);
            }
        },

        closePanel() {
            this.panel = null;
            this.editingId = null;
            this.errors = {};
        },

        get editingRecord() {
            if (!this.editingId) {
                return null;
            }

            const entity = this.panel === 'project' ? 'projects' : 'properties';

            return this.$store.data.find(entity, this.editingId);
        },

        // ----- uploads -----

        async uploadProjectImage(event, record) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('image', file);

            try {
                const payload = await this.$store.uploads.start({
                    url: `/api/projects/${record.id}/image`,
                    formData,
                    label: `صورة «${record.name}»`,
                });

                this.$store.data.applyServerRecord('projects', payload.data);
            } catch (error) {
                alert(error.message);
            }

            event.target.value = '';
        },

        async uploadProjectPdf(event, record) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('pdf', file);

            try {
                const payload = await this.$store.uploads.start({
                    url: `/api/projects/${record.id}/pdf`,
                    formData,
                    label: `ملف «${record.name}»`,
                });

                this.$store.data.applyServerRecord('projects', payload.data);
            } catch (error) {
                alert(error.message);
            }

            event.target.value = '';
        },

        async uploadPropertyImages(event, record) {
            const files = Array.from(event.target.files ?? []);

            if (!files.length) {
                return;
            }

            const formData = new FormData();
            files.forEach((file) => formData.append('photos[]', file));

            try {
                const payload = await this.$store.uploads.start({
                    url: `/api/properties/${record.id}/images`,
                    formData,
                    label: `صور «${record.name}»`,
                });

                this.$store.data.applyServerRecord('properties', payload.data);
            } catch (error) {
                alert(error.message);
            }

            event.target.value = '';
        },

        async uploadPropertyPdf(event, record) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('pdf', file);

            try {
                const payload = await this.$store.uploads.start({
                    url: `/api/properties/${record.id}/pdf`,
                    formData,
                    label: `ملف «${record.name}»`,
                });

                this.$store.data.applyServerRecord('properties', payload.data);
            } catch (error) {
                alert(error.message);
            }

            event.target.value = '';
        },

        async removePropertyImage(record, image) {
            if (!confirm('هل تريد حذف هذه الصورة؟')) {
                return;
            }

            try {
                await request('DELETE', `/api/property-images/${image.id}`);
                record.images = (record.images ?? []).filter((candidate) => candidate.id !== image.id);
                this.$store.data.persist('properties');
            } catch (error) {
                alert(error.message);
            }
        },
    };
}
