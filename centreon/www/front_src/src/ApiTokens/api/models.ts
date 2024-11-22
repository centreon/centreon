import {
  ListingParameters,
  QueryParameter,
  SearchParameter
} from '@centreon/ui';

export interface BuildListEndpoint {
  customQueryParameters?: Array<QueryParameter> | null;
  endpoint: string;
  parameters: ListingParameters;
}

export interface DeleteTokenEndpoint {
  tokenName: string;
  userId: number;
}

export interface Parameters {
  page: number;
  search: SearchParameter;
}
