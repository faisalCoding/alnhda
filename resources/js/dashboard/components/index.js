import articlesPage from './articles';
import leadsPage from './leads';
import overviewPage from './overview';
import projectsPage from './projects';
import visitorsPage from './visitors';
import whatsappPage from './whatsapp';

export default function registerComponents(Alpine) {
    Alpine.data('overviewPage', overviewPage);
    Alpine.data('projectsPage', projectsPage);
    Alpine.data('articlesPage', articlesPage);
    Alpine.data('visitorsPage', visitorsPage);
    Alpine.data('leadsPage', leadsPage);
    Alpine.data('whatsappPage', whatsappPage);
}
