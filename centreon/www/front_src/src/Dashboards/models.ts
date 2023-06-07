import { FormVariant } from '@centreon/ui/components';

export interface NamedEntity {
  id: number;
  name: string;
}

export enum DashboardRole {
  editor = 'editor',
  viewer = 'viewer'
}

export interface Dashboard extends NamedEntity {
  createdAt: string;
  createdBy: NamedEntity;
  description: string | null;
  ownRole: DashboardRole;
  updatedAt: string;
  updatedBy: NamedEntity;
}

export interface SelectedDashboard {
  dashboard: Dashboard | null;
  variant: FormVariant;
}

export enum ContactType {
  contact = 'contact',
  contactGroup = 'contact_group'
}

export interface DashboardShare extends NamedEntity {
  email?: string;
  fullname: string;
  role: DashboardRole;
  type: ContactType;
}
