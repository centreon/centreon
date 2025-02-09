export type NamedEntity = {
  id: number;
  name: string;
};

export interface HostGroupListItem extends NamedEntity {
  alias: string | null;
  enabledHostsCount: number;
  disabledHostsCount: number;
  iconUrl: string | null;
  isActivated: boolean;
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

export interface FiltersType {
  name: string;
  alias: string;
  enabled: boolean;
  disabled: boolean;
}
