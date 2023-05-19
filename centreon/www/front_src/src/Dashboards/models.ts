import { FormVariant } from '@centreon/ui/components';

export interface Dashboard {
  createdAt: string;
  description: string | null;
  id: number;
  name: string;
  updatedAt: string;
}

export interface SelectedDashboard {
  dashboard: Dashboard | null;
  variant: FormVariant;
}
