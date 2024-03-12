import { JsonDecoder } from 'ts.data.json';

import { MetricProps } from '../models';

const metricDecoder = JsonDecoder.object<MetricProps>(
  {
    criticalHighThreshold: JsonDecoder.nullable(JsonDecoder.number),
    criticalLowThreshold: JsonDecoder.nullable(JsonDecoder.number),
    currentValue: JsonDecoder.nullable(JsonDecoder.number),
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    unit: JsonDecoder.string,
    warningHighThreshold: JsonDecoder.nullable(JsonDecoder.number),
    warningLowThreshold: JsonDecoder.nullable(JsonDecoder.number)
  },
  'metric',
  {
    criticalHighThreshold: 'critical_high_threshold',
    criticalLowThreshold: 'critical_low_threshold',
    currentValue: 'current_value',
    warningHighThreshold: 'warning_high_threshold',
    warningLowThreshold: 'warning_low_threshold'
  }
);

export const metricsDecoder = JsonDecoder.array(metricDecoder, 'Metrics');
