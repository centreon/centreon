import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Metric, ServiceMetric } from '../models';

const serviceMetricDecoder = JsonDecoder.object<ServiceMetric>(
  {
    id: JsonDecoder.number,
    metrics: JsonDecoder.array(
      JsonDecoder.object<Metric>(
        {
          id: JsonDecoder.number,
          name: JsonDecoder.string,
          unit: JsonDecoder.string
        },
        'Metric'
      ),
      'Metrics'
    ),
    name: JsonDecoder.string
  },
  'Service Metric'
);

export const serviceMetricsDecoder = buildListingDecoder({
  entityDecoder: serviceMetricDecoder,
  entityDecoderName: 'Listing Service Metric',
  listingDecoderName: 'Service Metrics'
});
