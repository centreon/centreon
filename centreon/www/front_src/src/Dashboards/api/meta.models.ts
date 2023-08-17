/* eslint-disable typescript-sort-keys/interface */

/**
 * temporary generic for lists, will be migrated
 */
import { ListingParameters } from '@centreon/ui';

export type ListQueryParams = ListingParameters &
  Record<string, string | number>;

export type ListMeta = {
  limit: number;
  page: number;
  total: number;
};

export type List<TEntity> = {
  meta: ListMeta;
  result: Array<TEntity>;
};
