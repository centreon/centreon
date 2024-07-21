export type NamedEntity = {
  id: number | string;
  name: string;
};

export interface AdditionalConnectors extends NamedEntity {
  createdAt: string;
  createdBy: NamedEntity | null;
  description: string | null;
  name: string;
  type: string;
  updatedAt: string;
  updatedBy: NamedEntity | null;
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
