import { buildListingEndpoint } from '@centreon/ui';

export const hostGroupsListEndpoint = '/configuration/hosts/groups';

export const getHostGroupEndpoint = ({ id }): string =>
  `/configuration/hosts/groups/${id}`;

export const bulkDuplicateHostGroupEndpoint =
  '/configuration/hosts/groups/_duplicate';

export const bulkDeleteHostGroupEndpoint =
  '/configuration/hosts/groups/_delete';

export const bulkEnableHostGroupEndpoint =
  '/configuration/hosts/groups/_enable';

export const bulkDisableHostGroupEndpoint =
  '/configuration/hosts/groups/_disable';

export const hostListEndpoint = '/configuration/hosts';
export const resourceAccessRulesEndpoint =
  '/administration/resource-access/rules';

export const listImagesEndpoint = '/configuration/icons';

export const getListImagesSearchEndpoint = ({ search, page }): string =>
  buildListingEndpoint({
    baseEndpoint: listImagesEndpoint,
    parameters: {
      limit: 10,
      page,
      search
    }
  });

export const additionalConnectorsEndpoint =
  '/configuration/additional-connector-configurations';

export const getAdditionalConnectorEndpoint = ({ id }): string =>
  `/configuration/additional-connector-configurations/${id}`;

export const pollersEndpoint = '/configuration/monitoring-servers';

export const getPollersEndpoint = (parameters): string =>
  buildListingEndpoint({
    baseEndpoint: pollersEndpoint,
    parameters
  });
