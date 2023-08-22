import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Metric, ServiceMetric } from '../models';

const serviceMetricDecoder = JsonDecoder.object<ServiceMetric>(
  {
    id: JsonDecoder.number,
    metrics: JsonDecoder.array(
      JsonDecoder.object<Metric>(
        {
          criticalThreshold: JsonDecoder.nullable(JsonDecoder.number),
          id: JsonDecoder.number,
          name: JsonDecoder.string,
          unit: JsonDecoder.string,
          warningThreshold: JsonDecoder.nullable(JsonDecoder.number)
        },
        'Metric',
        {
          criticalThreshold: 'critical_threshold',
          warningThreshold: 'warning_threshold'
        }
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
