import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import persist from '@alpinejs/persist';
import registerComponents from './dashboard/components/index';
import { dataStore } from './dashboard/store';
import { startSync, syncStore } from './dashboard/sync';
import { uploadsStore } from './dashboard/uploads';

window.Alpine = Alpine;

Alpine.plugin(persist);
Alpine.plugin(focus);

Alpine.store('data', dataStore);
Alpine.store('sync', syncStore);
Alpine.store('uploads', uploadsStore);

registerComponents(Alpine);

Alpine.start();
startSync();
