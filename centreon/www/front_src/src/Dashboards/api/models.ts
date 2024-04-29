/* eslint-disable typescript-sort-keys/interface,sort-keys-fix/sort-keys-fix */

import { List } from './meta.models';

/**
 * resource types
 */

export const resource = {
  dashboard: 'dashboard',
  dashboards: 'dashboards',
  dashboardsContacts: 'dashboardsContacts',
  dashboardsContactGroups: 'dashboardsContactGroups',
  dashboardAccessRightsContactGroups: 'dashboardAccessRightsContactGroups',
  dashboardAccessRightsContacts: 'dashboardAccessRightsContacts'
} as const;

/**
 * base entity
 */

export type NamedEntity = {
  id: number | string;
  name: string;
};

export enum DashboardRole {
  editor = 'editor',
  viewer = 'viewer'
}

export enum ContactType {
  contact = 'contact',
  contactGroup = 'contact_group'
}

export interface UserRole {
  id: number;
  name: string;
  email?: string | null;
  role: DashboardRole;
}

export interface Shares {
  contacts: Array<UserRole>;
  contactGroups: Array<UserRole>;
}

export enum ShareType {
  Contact = 'contact',
  ContactGroup = 'contact_group'
}

/**
 * dashboard
 */

export type Dashboard = NamedEntity & {
  description: string | null;
  createdAt: string;
  updatedAt: string;
  createdBy: NamedEntity | null;
  updatedBy: NamedEntity | null;
  ownRole: DashboardRole;
  panels?: Array<DashboardPanel>;
  shares: Shares;
  refresh: {
    type: 'global' | 'manual';
    interval: number | null;
  };
};

export type PublicDashboard = NamedEntity & {
  description: string | null;
  panels?: Array<DashboardPanel>;
  refresh: {
    type: 'global' | 'manual';
    interval: number | null;
  };
};

export interface FormattedShare {
  id: number | string;
  role: DashboardRole;
  name: string;
  dashboardId: number | string;
  type: ShareType;
}

export interface FormattedDashboard extends Omit<Dashboard, 'shares'> {
  shares: Array<FormattedShare>;
}

export type CreateDashboardDto = Omit<
  Dashboard,
  'id' | 'createdAt' | 'updatedAt' | 'createdBy' | 'updatedBy' | 'ownRole'
>;

export type DeleteDashboardDto = Pick<Dashboard, 'id'>;

export const isDashboard = (value: unknown): value is Dashboard =>
  (value as Dashboard).id !== undefined;

export const isDashboardList = (value: unknown): value is List<Dashboard> =>
  value !== undefined &&
  Array.isArray((value as List<Dashboard>).result) &&
  (value as List<Dashboard>).result.every(isDashboard);

/**
 * dashboard panel
 */

export type DashboardPanel = NamedEntity & {
  layout: {
    height: number;
    minHeight: number;
    minWidth: number;
    width: number;
    x: number;
    y: number;
  };
  widgetSettings: {
    options: {
      [key: string]: unknown;
    };
    data: {
      [key: string]: unknown;
    };
  };
  widgetType: string;
};

/**
 * dashboards contacts and contact groups
 */

export type DashboardsContact = NamedEntity & {
  type: ContactType.contact;
};

export const isDashboardsContact = (
  value: unknown
): value is DashboardsContact =>
  (value as DashboardsContact).type === ContactType.contact;

export type DashboardsContactGroup = NamedEntity & {
  type: ContactType.contactGroup;
};

export const isDashboardsContactGroup = (
  value: unknown
): value is DashboardsContactGroup =>
  (value as DashboardsContactGroup).type === ContactType.contactGroup;

/**
 * dashboard access rights
 */

export type DashboardAccessRightsContact = NamedEntity & {
  email?: string;
  role: DashboardRole;
  type: ContactType.contact;
};

export type DashboardAccessRightsContactGroup = NamedEntity & {
  role: DashboardRole;
  type: ContactType.contactGroup;
};

export type CreateAccessRightDto = {
  dashboardId: NamedEntity['id'];
} & Pick<
  DashboardAccessRightsContact | DashboardAccessRightsContactGroup,
  'id' | 'role'
>;

export type UpdateAccessRightDto = CreateAccessRightDto;

export type DeleteAccessRightDto = {
  dashboardId: NamedEntity['id'];
} & Pick<NamedEntity, 'id'>;
