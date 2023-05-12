import { DashboardFormVariant } from '@centreon/ui';

export interface Dashboard {
  createdAt: string;
  description: string | null;
  id: number;
  name: string;
  updatedAt: string;
}

export interface SelectedDashboard {
  dashboard: Dashboard | null;
  variant: DashboardFormVariant;
}
