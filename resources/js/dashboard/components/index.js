import articlesPage from './articles';
import overviewPage from './overview';
import projectsPage from './projects';
import visitorsPage from './visitors';

export default function registerComponents(Alpine) {
    Alpine.data('overviewPage', overviewPage);
    Alpine.data('projectsPage', projectsPage);
    Alpine.data('articlesPage', articlesPage);
    Alpine.data('visitorsPage', visitorsPage);
}
