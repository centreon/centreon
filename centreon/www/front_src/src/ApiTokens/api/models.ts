import { ListingParameters, QueryParameter } from '@centreon/ui';

export interface BuildListEndpoint {
  customQueryParameters?: Array<QueryParameter> | null;
  endpoint: string;
  parameters: ListingParameters;
}

export interface Meta {
  tokenName: string;
  userId: number;
}

export interface DeletedToken {
  message: string | null;
  self: string;
  status: number;
}
