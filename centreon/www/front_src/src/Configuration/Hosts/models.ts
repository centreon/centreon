import type { Icon, NamedEntity } from '../ResourceForm/models';

export type { Icon, NamedEntity } from '../ResourceForm/models';

export type FormPoller = NamedEntity & {
  isDefault: boolean;
};

export interface HostListItem extends NamedEntity {
  alias?: string | null;
  address: string;
  icon?: Icon | null;
  isActivated: boolean;
  poller: NamedEntity;
  templates: Array<NamedEntity>;
}

// The single-select keys are the query parameters `useLoadData` sends them as.
export type Filters = {
  name: string;
  group_id: NamedEntity | null;
  template_id: NamedEntity | null;
  poller_id: NamedEntity | null;
  enabled: boolean;
  disabled: boolean;
};
