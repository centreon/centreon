import { Fields, SortOrder, SortParameters } from './filter/models';

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
