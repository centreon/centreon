export interface ListingMeta {
  limit: number;
  page: number;
  total: number;
}

export interface Listing<TEntity> {
  meta: ListingMeta;
  result: Array<TEntity>;
}
