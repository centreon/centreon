export const baseEndpoint = 'http://localhost:5005/centreon/api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;
export const getDashboardSharesEndpoint = (id?: number): string =>
  `${baseEndpoint}/configuration/dashboards/${id}/shares`;
