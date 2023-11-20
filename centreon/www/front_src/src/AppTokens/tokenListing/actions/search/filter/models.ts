import { SearchParameter } from '@centreon/ui';

export enum SortOrder {
  asc = 'asc',
  desc = 'desc'
}

export enum Fields {
  'creation.id' = 'creator.id',
  'creation.name' = 'creator.name',
  'creation_date' = 'creation_date',
  'expiration_date' = 'expiration_date',
  'is_revoked' = 'is_revoked',
  'token_name' = 'token_name',
  'user.id' = 'user.id',
  'user.name' = 'user.name'
}

export const DefaultSortBy = { [Fields.token_name]: SortOrder.asc };

export const DefaultParameters = { limit: 10, page: 1, sort: DefaultSortBy };

type sortParametersKey = keyof typeof Fields;

export type SortParameters = {
  [key: sortParametersKey]: SortOrder;
};

export interface TokenFilter {
  limit: number;
  page: number;
  search?: SearchParameter;
  sort: SortParameters;
}
