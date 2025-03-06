import { JsonDecoder } from 'ts.data.json';

import { MetricsTop, Resource } from '../models';

export const metricsTopDecoder = JsonDecoder.object<MetricsTop>(
  {
    name: JsonDecoder.string,
    resources: JsonDecoder.array(
      JsonDecoder.object<Resource>(
        {
          criticalHighThreshold: JsonDecoder.nullable(JsonDecoder.number),
          criticalLowThreshold: JsonDecoder.nullable(JsonDecoder.number),
          currentValue: JsonDecoder.nullable(JsonDecoder.number),
          id: JsonDecoder.number,
          max: JsonDecoder.nullable(JsonDecoder.number),
          min: JsonDecoder.nullable(JsonDecoder.number),
          name: JsonDecoder.string,
          parentName: JsonDecoder.string,
          uuid: JsonDecoder.nullable(JsonDecoder.string),
          warningHighThreshold: JsonDecoder.nullable(JsonDecoder.number),
          warningLowThreshold: JsonDecoder.nullable(JsonDecoder.number)
        },
        'resource',
        {
          criticalHighThreshold: 'critical_high_threshold',
          criticalLowThreshold: 'critical_low_threshold',
          currentValue: 'current_value',
          parentName: 'parent_name',
          warningHighThreshold: 'warning_high_threshold',
          warningLowThreshold: 'warning_low_threshold'
        }
      ),
      'resources'
    ),
    unit: JsonDecoder.string
  },
  'metricsTop'
);
