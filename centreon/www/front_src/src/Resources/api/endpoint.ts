import { baseEndpoint } from '../../api/endpoint';

const monitoringEndpoint = `${baseEndpoint}/monitoring`;
const resourcesEndpoint = `${monitoringEndpoint}/resources`;
const hostsEndpoint = `${monitoringEndpoint}/resources/hosts`;
const userEndpoint =
  './api/internal.php?object=centreon_topcounter&action=user';

export { monitoringEndpoint, resourcesEndpoint, userEndpoint, hostsEndpoint };
