export type NamedEntity = {
  id: number;
  name: string;
};

export interface AdditionalConnectorListItem extends NamedEntity {
  createdAt: string;
  createdBy: NamedEntity;
  description: string | null;
  name: string;
  type: string;
  updatedAt: string | null;
  updatedBy: NamedEntity | null;
}

export interface DialogState {
  connector: AdditionalConnectorListItem | null;
  isOpen: boolean;
  variant: 'create' | 'update';
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
  pollers: Array<NamedEntity>;
  types: Array<NamedEntity>;
}
