export interface ListingMeta {
  // Set by the APIs that do not count their items: whether another page follows
  hasNextPage?: boolean;
  limit: number;
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
