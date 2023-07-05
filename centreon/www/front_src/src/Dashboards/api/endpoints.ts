export const baseEndpoint = './api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;

export const getDashboardEndpoint = (id?: string): string =>
  `${dashboardsEndpoint}/${id}`;
