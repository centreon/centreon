import { NamedEntity } from './models';

export const baseEndpoint = './api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;

export const getPublicDashboardEndpoint = ({
  playlistID,
  dashboardId
}): string =>
  `/it-edition-extensions/monitoring/dashboards/playlists/${playlistID}/dashboards/${dashboardId}`;

export const getDashboardEndpoint = (id?: NamedEntity['id']): string =>
  `${dashboardsEndpoint}/${id}`;

export const getDashboardAccessRightsContactEndpoint = (
  dashboardId?: NamedEntity['id'],
  id?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights/contacts/${id}`;

export const getDashboardAccessRightsContactGroupEndpoint = (
  dashboardId?: NamedEntity['id'],
  id?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights/contactgroups/${id}`;

export const dashboardsContactsEndpoint = '/configuration/dashboards/contacts';

export const dashboardsContactGroupsEndpoint =
  '/configuration/dashboards/contactgroups';

export const playlistsEndpoint = '/configuration/dashboards/playlists';

export const playlistEndpoint = (id: number | string): string =>
  `/configuration/dashboards/playlists/${id}`;

export const dashboardSharesEndpoint = (id: number | string): string =>
  `/configuration/dashboards/${id}/shares`;

export const playlistsByDashboardEndpoint = (id: number | string): string =>
  `/it-edition-extensions/configuration/dashboards/${id}/playlists`;