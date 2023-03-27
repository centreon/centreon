import { baseEndpoint } from '../../api/endpoint';

export const serviceStatusEndpoint =
  './api/internal.php?object=centreon_topcounter&action=servicesStatus';
export const hostStatusEndpoint =
  './api/internal.php?object=centreon_topcounter&action=hosts_status';
export const pollerListIssuesEndPoint =
  './api/internal.php?object=centreon_topcounter&action=pollersListIssues';
export const exportAndReloadConfigurationEndpoint = `${baseEndpoint}/configuration/monitoring-servers/generate-and-reload`;
export const userEndpoint =
  './api/internal.php?object=centreon_topcounter&action=user';
