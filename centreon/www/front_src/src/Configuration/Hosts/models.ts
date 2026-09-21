export type NamedEntity = {
  id: number;
  name: string;
};

export interface HostListItem extends NamedEntity {
  alias?: string | null;
  address: string;
  isActivated: boolean;
  poller: NamedEntity;
  templates: Array<NamedEntity>;
}

export type Filters = {
  name: string;
};
