import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { Metric, ServiceMetric } from '../models';

const serviceMetricDecoder = JsonDecoder.object<ServiceMetric>(
  {
    id: JsonDecoder.number,
    metrics: JsonDecoder.array(
      JsonDecoder.object<Metric>(
        {
          criticalHighThreshold: JsonDecoder.nullable(JsonDecoder.number),
          criticalLowThreshold: JsonDecoder.nullable(JsonDecoder.number),
          id: JsonDecoder.number,
          name: JsonDecoder.string,
          unit: JsonDecoder.string,
          warningHighThreshold: JsonDecoder.nullable(JsonDecoder.number),
          warningLowThreshold: JsonDecoder.nullable(JsonDecoder.number)
        },
        'Metric',
        {
          criticalHighThreshold: 'critical_high_threshold',
          criticalLowThreshold: 'critical_low_threshold',
          warningHighThreshold: 'warning_high_threshold',
          warningLowThreshold: 'warning_low_threshold'
        }
      ),
      'Metrics'
    ),
    name: JsonDecoder.string,
    parentName: JsonDecoder.string,
    uuid: JsonDecoder.string
  },
  'Service Metric',
  {
    parentName: 'parent_name'
  }
);

export const serviceMetricsDecoder = buildListingDecoder({
  entityDecoder: serviceMetricDecoder,
  entityDecoderName: 'Listing Service Metric',
  listingDecoderName: 'Service Metrics'
});
