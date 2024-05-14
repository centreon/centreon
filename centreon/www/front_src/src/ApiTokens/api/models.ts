import { ListingParameters, QueryParameter } from '@centreon/ui';

export interface BuildListEndpoint {
  customQueryParameters?: Array<QueryParameter> | null;
  endpoint: string;
  parameters: ListingParameters;
}

export interface DeleteTokenEndpoint {
  tokenName: string;
  userId: number;
}

export interface PatchTokenEndpoint {
  tokenName: string;
  userId: number;
}
