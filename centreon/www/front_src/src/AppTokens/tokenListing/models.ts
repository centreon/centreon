import { RefetchOptions, QueryObserverResult } from '@tanstack/react-query';

import {
  Fields,
  SortOrder,
  SortParameters
} from './actions/search/filter/models';

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
  onSort: (sortParameters: SortParameters) => void;
  refetch: (
    options?: RefetchOptions | undefined
  ) => Promise<QueryObserverResult<unknown, Error>>;
  sortField: Fields;
  sortOrder: SortOrder;
}

export interface PersonalInformation {
  id: number;
  name: string;
}

export interface Token {
  creation_date: string;
  creator: PersonalInformation;
  expiration_date: string;
  is_revoked: boolean;
  name: string;
  user: PersonalInformation;
}
