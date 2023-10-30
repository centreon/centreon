import { CancelToken } from 'axios';

import { buildListingEndpoint, getData } from '@centreon/ui';
import type { ListingParameters } from '@centreon/ui';

import { MetaServiceMetricListing } from '../models';

interface ListMetaServiceMetricsProps {
  endpoint: string;
  parameters: ListingParameters;
}

const buildListMetaServiceMetricsEndpoint = ({
  endpoint,
  parameters
}: ListMetaServiceMetricsProps): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters
  });

const listMetaServiceMetrics =
  (cancelToken: CancelToken) =>
  ({
    endpoint,
    parameters
  }: ListMetaServiceMetricsProps): Promise<MetaServiceMetricListing> => {
    return getData<MetaServiceMetricListing>(cancelToken)({
      endpoint: buildListMetaServiceMetricsEndpoint({ endpoint, parameters })
    });
  };

export { listMetaServiceMetrics, buildListMetaServiceMetricsEndpoint };
