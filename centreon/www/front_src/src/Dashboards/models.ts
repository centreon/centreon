import { FormVariant } from '@centreon/ui/components';

export interface NamedEntity {
  id: number;
  name: string;
}

export enum DahboardRole {
  editor = 'editor',
  viewer = 'viewer'
}

export interface Dashboard extends NamedEntity {
  createdAt: string;
  createdBy: NamedEntity;
  description: string | null;
  ownRole: DahboardRole;
  updatedAt: string;
  updatedBy: NamedEntity;
}

export interface SelectedDashboard {
  dashboard: Dashboard | null;
  variant: FormVariant;
}
