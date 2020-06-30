export interface ListingMeta {
  page: number;
  limit: number;
  total: number;
}

export interface Listing<TEntity> {
  result: Array<TEntity>;
  meta: ListingMeta;
}
