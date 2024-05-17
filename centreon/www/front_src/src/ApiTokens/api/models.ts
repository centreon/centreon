import { ListingParameters, QueryParameter } from '@centreon/ui';

export interface BuildListEndpoint {
  customQueryParameters?: Array<QueryParameter> | null;
  endpoint: string;
  parameters: ListingParameters;
}

export interface TokenEndpoint {
  tokenName: string;
  userId: number;
}
