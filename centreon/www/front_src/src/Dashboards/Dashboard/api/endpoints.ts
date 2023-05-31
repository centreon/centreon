import { dashboardsEndpoint } from '../../api/endpoints';

export const getDashboardEndpoint = (id?: string): string =>
  `${dashboardsEndpoint}/${id}`;
