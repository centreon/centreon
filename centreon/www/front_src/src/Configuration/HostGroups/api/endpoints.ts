export const hostGroupsListEndpoint = '/configuration/hosts/groups';
export const hostListEndpoint = '/configuration/hosts';

export const getHostGroupEndpoint = ({ id }): string =>
  `/configuration/hosts/groups/${id}`;

export const duplicateHostGroupEndpoint =
  '/configuration/hosts/groups/_duplicate';

export const bulkDeleteHostGroupEndpoint =
  '/configuration/hosts/groups/_delete';
