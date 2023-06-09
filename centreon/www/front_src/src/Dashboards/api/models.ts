/* eslint-disable typescript-sort-keys/interface */

export const resource = {
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

export const isDashboard = (value: unknown): value is Dashboard =>
  (value as Dashboard).id !== undefined;
