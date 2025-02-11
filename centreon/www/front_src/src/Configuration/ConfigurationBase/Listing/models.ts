export type NamedEntity = {
  id: number;
  name: string;
};

export interface IconType {
  id: number;
  name: string;
  url: string;
}
export interface HostGroupListItem extends NamedEntity {
  alias: string | null;
  enabledHostsCount: number;
  disabledHostsCount: number;
  icon: IconType | null;
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
