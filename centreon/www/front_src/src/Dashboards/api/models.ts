/* eslint-disable typescript-sort-keys/interface */

import { ListingParameters } from '@centreon/ui';

export const resource = {
  dashboard: 'dashboard',
  dashboards: 'dashboards'
} as const;

export type Dashboard = {
  id: number | string;
  name: string;
  description: string | null;
  createdAt: string;
  updatedAt: string;
};

export type CreateDashboardDto = Omit<
  Dashboard,
  'id' | 'createdAt' | 'updatedAt'
>;

export type UpdateDashboardDto = Omit<
  Dashboard,
  'id' | 'createdAt' | 'updatedAt'
>;

export type DeleteDashboardDto = Pick<Dashboard, 'id'>;

export const isDashboard = (value: unknown): value is Dashboard =>
  (value as Dashboard).id !== undefined;

/**
 * temporary generic for lists, will be migrated
 */

export type ListQueryParams = ListingParameters &
  Record<string, string | number>;

export type ListMeta = {
  limit: number;
  page: number;
  total: number;
};

export type List<TEntity> = {
  meta: ListMeta;
  result: Array<TEntity>;
};

export const isDashboardList = (value: unknown): value is List<Dashboard> =>
  Array.isArray((value as List<Dashboard>).result) &&
  (value as List<Dashboard>).result.every(isDashboard);
