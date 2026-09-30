export type NamedEntity = {
  id: number;
  name: string;
};

export type Icon = NamedEntity & {
  url: string;
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

// What the form opens on. The sections still to come each add their own.
export interface HostDetail {
  address: string;
  name: string;
  poller: NamedEntity;
}
