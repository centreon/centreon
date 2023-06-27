export const baseEndpoint = './api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;

export const getDashboardEndpoint = (id?: string | number): string =>
  `${dashboardsEndpoint}/${id}`;

export const getDashboardAccessRightsEndpoint = (
  id?: string | number
): string => `${baseEndpoint}/configuration/dashboards/${id}/access_rights`;
