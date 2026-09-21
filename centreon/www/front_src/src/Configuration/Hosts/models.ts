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

export type Filters = {
  name: string;
};
