import { ListingParameters, QueryParameter } from '@centreon/ui';

export interface BuildListTokensEndpoint {
  customQueryParameters?: Array<QueryParameter>;
  parameters: ListingParameters;
}
