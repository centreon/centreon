export type ListMeta = {
  limit: number;
  page: number;
  total: number;
};
export type List<TEntity> = {
  meta: ListMeta;
  result: Array<TEntity>;
};

export interface ModalState {
  id: number | null;
  isOpen: boolean;
  mode: 'add' | 'edit';
}
