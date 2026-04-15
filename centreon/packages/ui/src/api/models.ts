export interface ListingMeta {
  limit: number;
  next_cursor?: string | null;
  page: number;
  total: number;
}

export interface Listing<TEntity> {
  meta: ListingMeta;
  result: Array<TEntity>;
}

export interface ListingMap<TEntity> {
  content: Array<TEntity>;
  totalPages: number;
  totalElements: number;
  size: number;
  number: number;
  numberOfElements: number;
}
