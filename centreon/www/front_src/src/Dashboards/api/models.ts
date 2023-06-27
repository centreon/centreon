/* eslint-disable typescript-sort-keys/interface */

import { List } from './meta.models';

/**
 * resource types
 */

export const resource = {
  dashboard: 'dashboard',
  dashboards: 'dashboards'
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

/**
 * dashboard
 */

export type Dashboard = NamedEntity & {
  description: string | null;
  createdAt: string;
  updatedAt: string;
  createdBy: NamedEntity;
  updatedBy: NamedEntity;
  ownRole: DashboardRole;
  panels?: Array<DashboardPanel>;
};

export type CreateDashboardDto = Omit<
  Dashboard,
  'id' | 'createdAt' | 'updatedAt' | 'createdBy' | 'updatedBy' | 'ownRole'
>;

export type UpdateDashboardDto = Omit<
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
    [key: string]: unknown;
  };
  widgetType: string;
};
