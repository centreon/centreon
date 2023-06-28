import {
  ContactType,
  DashboardContactAccessRights,
  DashboardRole
} from '../api/models';

// FIXME separate form state from api models
export interface DashboardShareForm extends DashboardContactAccessRights {
  isRemoved: boolean;
}

export interface DashboardShareToAPI {
  id: number;
  role: DashboardRole;
  type: ContactType;
}
