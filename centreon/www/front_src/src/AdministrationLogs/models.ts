import type { SelectEntry } from '@centreon/ui';

export enum Action {
  Add = 'Add',
  Delete = 'Delete',
  Disable = 'Disable',
  Enable = 'Enable',
  MassChange = 'MassChange',
  Update = 'Update'
}

export enum Period {
  All = 'all',
  Custom = 'custom',
  Last24Hours = 'last_24_hours',
  Last30Days = 'last_30_days',
  Last7Days = 'last_7_days',
  LastHour = 'last_hour'
}

export interface TargetParent {
  names: Array<string>;
  type: string;
}

export interface Target {
  id: number;
  name: string;
  parent?: TargetParent | null;
  type: string;
}

export interface Actor {
  id: number;
  name?: string | null;
}

export interface ActivityLog {
  action: Action;
  actor?: Actor | null;
  date: string;
  hasChanges: boolean;
  id: number;
  target: Target;
  // Name of the API token the change was made with, null when made from the UI
  tokenName?: string | null;
}

export interface Change {
  after?: string | null;
  before?: string | null;
  field: string;
}

export interface Filters {
  actions: Array<SelectEntry>;
  actors: Array<SelectEntry>;
  from: Date | null;
  period: Period;
  search: string;
  targetTypes: Array<SelectEntry>;
  to: Date | null;
  tokens: Array<SelectEntry>;
}
