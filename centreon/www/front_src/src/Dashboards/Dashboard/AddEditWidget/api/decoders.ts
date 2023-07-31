import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { NamedEntity, ServiceMetric } from '../models';

const serviceMetricDecoder = JsonDecoder.object<ServiceMetric>(
  {
    metrics: JsonDecoder.array(
      JsonDecoder.object<NamedEntity>(
        {
          id: JsonDecoder.number,
          name: JsonDecoder.string
        },
        'Metric'
      ),
      'Metrics'
    ),
    resourceName: JsonDecoder.string,
    serviceId: JsonDecoder.number
  },
  'Service Metric',
  {
    resourceName: 'resource_name',
    serviceId: 'service_id'
  }
);

export const serviceMetricsDecoder = buildListingDecoder({
  entityDecoder: serviceMetricDecoder,
  entityDecoderName: 'Listing Service Metric',
  listingDecoderName: 'Service Metrics'
});
