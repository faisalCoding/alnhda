import articlesPage from './articles';
import leadsPage from './leads';
import overviewPage from './overview';
import projectsPage from './projects';
import socialAccountsPage from './socialAccounts';
import visitorsPage from './visitors';
import whatsappMessagesPage from './whatsappMessages';
import whatsappPage from './whatsapp';

export default function registerComponents(Alpine) {
    Alpine.data('overviewPage', overviewPage);
    Alpine.data('projectsPage', projectsPage);
    Alpine.data('articlesPage', articlesPage);
    Alpine.data('visitorsPage', visitorsPage);
    Alpine.data('leadsPage', leadsPage);
    Alpine.data('socialAccountsPage', socialAccountsPage);
    Alpine.data('whatsappPage', whatsappPage);
    Alpine.data('whatsappMessagesPage', whatsappMessagesPage);
}
