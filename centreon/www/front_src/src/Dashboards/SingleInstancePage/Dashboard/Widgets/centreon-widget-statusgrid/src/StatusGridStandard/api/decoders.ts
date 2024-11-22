import { JsonDecoder } from 'ts.data.json';

import {
  BooleanRule,
  BusinessActivity,
  CalculationMethod,
  Impact,
  Indicator,
  IndicatorResource,
  MetricProps,
  Status
} from '../models';

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

const statusDecoder = JsonDecoder.object<Status>(
  {
    code: JsonDecoder.number,
    name: JsonDecoder.string,
    severityCode: JsonDecoder.number
  },
  'status',
  {
    severityCode: 'severity_code'
  }
);

const resourceDecoder = JsonDecoder.object<IndicatorResource>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    parentId: JsonDecoder.nullable(JsonDecoder.number),
    parentName: JsonDecoder.nullable(JsonDecoder.string)
  },
  'status',
  {
    parentId: 'parent_id',
    parentName: 'parent_name'
  }
);

const impactDecoder = JsonDecoder.object<Impact>(
  {
    critical: JsonDecoder.nullable(JsonDecoder.number),
    unknown: JsonDecoder.nullable(JsonDecoder.number),
    warning: JsonDecoder.nullable(JsonDecoder.number)
  },
  'impact'
);

const indicatorDecoder = JsonDecoder.object<Indicator>(
  {
    id: JsonDecoder.number,
    impact: JsonDecoder.nullable(impactDecoder),
    name: JsonDecoder.string,
    resource: JsonDecoder.nullable(resourceDecoder),
    status: statusDecoder,
    type: JsonDecoder.string
  },
  'Indicator'
);

const calculationMethodDecoder = JsonDecoder.object<CalculationMethod>(
  {
    criticalThreshold: JsonDecoder.nullable(JsonDecoder.number),
    id: JsonDecoder.number,
    isPercentage: JsonDecoder.nullable(JsonDecoder.boolean),
    name: JsonDecoder.string,
    warningThreshold: JsonDecoder.nullable(JsonDecoder.number)
  },
  'calculationMethod',
  {
    criticalThreshold: 'critical_threshold',
    isPercentage: 'is_percentage',
    warningThreshold: 'warning_threshold'
  }
);

export const businessActivityDecoder = JsonDecoder.object<BusinessActivity>(
  {
    calculationMethod: calculationMethodDecoder,
    id: JsonDecoder.number,
    indicators: JsonDecoder.array(indicatorDecoder, 'indicators'),
    infrastructureView: JsonDecoder.nullable(JsonDecoder.string),
    name: JsonDecoder.string,
    status: statusDecoder
  },
  'BusinessActivity',
  {
    calculationMethod: 'calculation_method',
    infrastructureView: 'infrastructure_view'
  }
);

export const booleanRuleDecoder = JsonDecoder.object<BooleanRule>(
  {
    expressionStatus: JsonDecoder.boolean,
    id: JsonDecoder.number,
    isImpactingWhenTrue: JsonDecoder.boolean,
    name: JsonDecoder.string,
    status: statusDecoder
  },
  'status',
  {
    expressionStatus: 'expression_status',
    isImpactingWhenTrue: 'is_impacting_when_expression_true'
  }
);

export const metricsDecoder = JsonDecoder.array(metricDecoder, 'Metrics');
