<<<<<<< HEAD
import { apiBase, versionApi } from '../commons';

const apiMonitoringBeta = `${apiBase}/beta/monitoring`;
const apiMonitoring = `${apiBase}/${versionApi}/monitoring`;

export { apiMonitoringBeta, apiMonitoring };
=======
const apiBase = `${Cypress.config().baseUrl}/centreon/api`;

const apiActionV1 = `${apiBase}/index.php`;

const versionApi = 'latest';
const apiFilterResources = `${apiBase}/${versionApi}/users/filters/events-view`;

const apiLoginV2 = `${apiBase}/${versionApi}/login`;
const apiMonitoringBeta = `${apiBase}/beta/monitoring`;
const apiMonitoring = `${apiBase}/${versionApi}/monitoring`;

export {
  apiActionV1,
  apiFilterResources,
  apiLoginV2,
  apiMonitoringBeta,
  apiMonitoring,
};
>>>>>>> centreon/dev-21.10.x
