import { crudPage } from './simpleCrud';

export default function backlinksPage() {
    return {
        ...crudPage('/api/backlinks', { id: null, name: '', url: '', target_url: '', visits: 0 }),

        get visible() {
            return this.records.filter((record) => this.matches(record, record.name, record.url, record.target_url));
        },

        get totalVisits() {
            return this.records.reduce((sum, record) => sum + (record.visits ?? 0), 0);
        },
    };
}
