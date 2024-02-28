import { ListingParameters, QueryParameter } from '@centreon/ui';

export interface BuildListEndpoint {
  customQueryParameters?: Array<QueryParameter>;
  endpoint: string;
  parameters: ListingParameters;
}

export interface DeleteTokenEndpoint {
  tokenName: string;
  userId: number;
}
