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

// The single-select filter keys are the query parameters the listing endpoint
// expects, because that is what `useLoadData` sends them as.
export type Filters = {
  name: string;
  group_id: NamedEntity | null;
  template_id: NamedEntity | null;
  enabled: boolean;
  disabled: boolean;
};
