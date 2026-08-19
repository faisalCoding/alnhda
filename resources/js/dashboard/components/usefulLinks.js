import { crudPage } from './simpleCrud';

export default function usefulLinksPage() {
    return {
        ...crudPage('/api/useful-links', { id: null, name: '', url: '', benefit: '' }),

        get visible() {
            return this.records.filter((record) => this.matches(record, record.name, record.url, record.benefit));
        },
    };
}
