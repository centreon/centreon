export const baseEndpoint = './api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;
export const getDashboardSharesEndpoint = (id?: number): string =>
  `${baseEndpoint}/configuration/dashboards/${id}/shares`;
