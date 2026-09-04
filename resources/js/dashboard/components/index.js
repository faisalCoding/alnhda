import accountsPage from './accounts';
import advertisingLicencesPage from './advertisingLicences';
import articlesPage from './articles';
import backlinksPage from './backlinks';
import collectionsPage from './collections';
import homeContentPage from './homeContent';
import leadsPage from './leads';
import marketingToolsPage from './marketingTools';
import overviewPage from './overview';
import projectsPage from './projects';
import seoPage from './seo';
import subscriptionsPage from './subscriptions';
import trafficPage from './traffic';
import usefulLinksPage from './usefulLinks';
import visitorsPage from './visitors';
import weeklyTasksPage from './weeklyTasks';
import whatsappMessagesPage from './whatsappMessages';
import whatsappPage from './whatsapp';

export default function registerComponents(Alpine) {
    Alpine.data('overviewPage', overviewPage);
    Alpine.data('projectsPage', projectsPage);
    Alpine.data('articlesPage', articlesPage);
    Alpine.data('collectionsPage', collectionsPage);
    Alpine.data('homeContentPage', homeContentPage);
    Alpine.data('trafficPage', trafficPage);
    Alpine.data('visitorsPage', visitorsPage);
    Alpine.data('leadsPage', leadsPage);
    Alpine.data('accountsPage', accountsPage);
    Alpine.data('advertisingLicencesPage', advertisingLicencesPage);
    Alpine.data('seoPage', seoPage);
    Alpine.data('subscriptionsPage', subscriptionsPage);
    Alpine.data('usefulLinksPage', usefulLinksPage);
    Alpine.data('backlinksPage', backlinksPage);
    Alpine.data('marketingToolsPage', marketingToolsPage);
    Alpine.data('weeklyTasksPage', weeklyTasksPage);
    Alpine.data('whatsappPage', whatsappPage);
    Alpine.data('whatsappMessagesPage', whatsappMessagesPage);
}
