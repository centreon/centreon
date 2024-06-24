import { QueryObserverResult, RefetchOptions } from '@tanstack/react-query';

import { Duration } from '../TokenCreation/models';

import { Fields, SortOrder } from './Actions/Filter/models';

export interface DataListing {
  isError: boolean;
  isLoading: boolean;
  limit?: number;
  page?: number;
  rows?: Array<Token>;
  total?: number;
}
export interface UseTokenListing {
  changeLimit: (value: number) => void;
  changePage: (value: number) => void;
  dataListing: DataListing;
  isRefetching: boolean;
  onSort: (sortParams: SortParams) => void;
  refetch: (
    options?: RefetchOptions | undefined
  ) => Promise<QueryObserverResult<unknown, Error>>;
  sortOrder: SortOrder;
  sortedField: Fields;
}

export interface PersonalInformation {
  id: number;
  name: string;
}

export interface Token {
  creationDate: string;
  creator: PersonalInformation;
  expirationDate: string;
  isRevoked: boolean;
  name: string;
  user: PersonalInformation;
}
export interface Row {
  row: Token;
}

export interface SortParams {
  sortField: string;
  sortOrder: string;
}

export interface CreateTokenFormValues {
  customizeDate: null | Date;
  duration: Omit<Duration, 'unit'> | null;
  tokenName: string;
  user: PersonalInformation | null;
}
