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

export const createPollerEndpoint = '/configuration/monitoring-servers';

export const getPollerRegistrationCommandEndpoint = (
  pollerId: number
): string =>
  `/configuration/monitoring-servers/${pollerId}/registration-command`;

export const exportPollerConfigurationEndpoint = (pollerId: number): string =>
  `/configuration/monitoring-servers/${pollerId}/generate-and-reload`;
