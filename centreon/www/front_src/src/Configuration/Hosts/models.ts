export type NamedEntity = {
  id: number;
  name: string;
};

export type Icon = NamedEntity & { url: string };

export interface HostListItem extends NamedEntity {
  alias?: string | null;
  address: string;
  isActivated: boolean;
  poller: NamedEntity;
  templates: Array<NamedEntity>;
  icon?: Icon | null;
}

export type Filters = {
  name: string;
};
