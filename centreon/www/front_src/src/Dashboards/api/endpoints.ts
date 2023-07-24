import { NamedEntity } from './models';

export const baseEndpoint = './api/latest';

export const dashboardsEndpoint = `${baseEndpoint}/configuration/dashboards`;

export const getDashboardEndpoint = (id?: NamedEntity['id']): string =>
  `${dashboardsEndpoint}/${id}`;

export const getDashboardAccessRightsEndpoint = (
  dashboardId?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights`;

export const getDashboardAccessRightsContactsEndpoint = (
  dashboardId?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights/contacts`;

export const getDashboardAccessRightsContactEndpoint = (
  dashboardId?: NamedEntity['id'],
  id?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights/contacts/${id}`;

export const getDashboardAccessRightsContactGroupsEndpoint = (
  dashboardId?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights/contactgroups`;

export const getDashboardAccessRightsContactGroupEndpoint = (
  dashboardId?: NamedEntity['id'],
  id?: NamedEntity['id']
): string =>
  `${baseEndpoint}/configuration/dashboards/${dashboardId}/access_rights/contactgroups/${id}`;

export const dashboardsContactsEndpoint = `${baseEndpoint}/configuration/dashboards/contacts`;

export const dashboardsContactGroupsEndpoint = `${baseEndpoint}/configuration/dashboards/contactgroups`;
