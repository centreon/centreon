import { dashboardsEndpoint } from '../../api/endpoints';

export const getPanelsEndpoint = (id?: string): string =>
  `${dashboardsEndpoint}/${id}/panels`;
