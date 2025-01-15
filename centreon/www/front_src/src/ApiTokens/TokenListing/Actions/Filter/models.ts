import { SearchParameter } from '@centreon/ui';

export enum SortOrder {
  asc = 'asc',
  desc = 'desc'
}

export enum Fields {
  CreationDate = 'creation_date',
  CreatorId = 'creator.id',
  CreatorName = 'creator.name',
  ExpirationDate = 'expiration_date',
  IsRevoked = 'is_revoked',
  TokenName = 'token_name',
  UserId = 'user.id',
  UserName = 'user.name'
}

export const DefaultSortBy = { [Fields.TokenName]: SortOrder.asc };

export const DefaultParameters = { limit: 10, page: 1, sort: DefaultSortBy };

export type SortParameters = {
  [key: string]: SortOrder;
};

export interface TokenFilter {
  limit: number;
  page: number;
  search?: SearchParameter;
  sort: SortParameters;
}

export interface User {
  id: number;
  name: string;
}

export enum Property {
  in = 'In',
  last = 'Last'
}
