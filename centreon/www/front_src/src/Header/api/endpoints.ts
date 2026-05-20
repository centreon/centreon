import { buildListingEndpoint, type ListingParameters } from '@centreon/ui';

import dayjs from 'dayjs';

import { baseEndpoint } from '../../api/endpoint';

export const serviceStatusEndpoint =
  './api/internal.php?object=centreon_topcounter&action=servicesStatus';
export const hostStatusEndpoint =
  './api/internal.php?object=centreon_topcounter&action=hosts_status';
export const pollerListIssuesEndPoint =
  './api/internal.php?object=centreon_topcounter&action=pollersListIssues';
export const exportAndReloadConfigurationEndpoint = `${baseEndpoint}/configuration/monitoring-servers/generate-and-reload`;
export const getExportConfigEndpoint = (id: number) =>
  `/configuration/monitoring-servers/${id}/generate-and-reload`;

export const userEndpoint =
  './api/internal.php?object=centreon_topcounter&action=user';

export const createPollerEndpoint = '/configuration/pollers';

export const listTokensEndpoint = '/administration/tokens';

export const tokensSearchConditions = [
  {
    field: 'type',
    values: {
      $eq: 'poller'
    }
  },
  {
    field: 'is_revoked',
    values: {
      $eq: false
    }
  },
  {
    field: 'expiration_date',
    values: {
      $eq: null,
      $ge: dayjs(Date.now())
    }
  }
];

export const getTokensEndpoint = (
  parameters: Omit<ListingParameters, 'apiFormat'>
): string => {
  return buildListingEndpoint({
    baseEndpoint: listTokensEndpoint,
    parameters: {
      ...parameters,
      search: {
        conditions: [
          ...(parameters?.search?.conditions || []),
          ...tokensSearchConditions
        ]
      }
    } as Omit<ListingParameters, 'apiFormat' | 'customQueryParameters'>
  });
};
