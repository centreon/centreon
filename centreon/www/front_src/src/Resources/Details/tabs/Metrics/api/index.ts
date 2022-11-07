import { CancelToken } from 'axios';

import { buildListingEndpoint, getData, ListingParameters } from '@centreon/ui';

import { MetaServiceMetricListing } from '../models';

interface ListMetaServiceMetricsProps {
  endpoint: string;
  parameters: ListingParameters;
}

const buildListMetaServiceMetricsEndpoint = ({
  endpoint,
  parameters,
}: ListMetaServiceMetricsProps): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters,
  });

const listMetaServiceMetrics =
  (cancelToken: CancelToken) =>
  ({
    endpoint,
    parameters,
  }: ListMetaServiceMetricsProps): Promise<MetaServiceMetricListing> => {
<<<<<<< HEAD
    return getData<MetaServiceMetricListing>(cancelToken)({
      endpoint: buildListMetaServiceMetricsEndpoint({ endpoint, parameters }),
    });
=======
    return getData<MetaServiceMetricListing>(cancelToken)(
      buildListMetaServiceMetricsEndpoint({ endpoint, parameters }),
    );
>>>>>>> centreon/dev-21.10.x
  };

export { listMetaServiceMetrics, buildListMetaServiceMetricsEndpoint };
