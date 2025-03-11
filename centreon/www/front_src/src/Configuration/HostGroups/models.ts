export type NamedEntity = {
  id: number;
  name: string;
};

type Icon = NamedEntity & { url: string };

export interface HostGroupListItem extends NamedEntity {
  alias: string | null;
  enabledHostsCount: number;
  disabledHostsCount: number;
  isActivated: boolean;
  icon: null | Icon;
}

export type ListMeta = {
  limit: number;
  page: number;
  total: number;
};
export type List<TEntity> = {
  meta: ListMeta;
  result: Array<TEntity>;
};
