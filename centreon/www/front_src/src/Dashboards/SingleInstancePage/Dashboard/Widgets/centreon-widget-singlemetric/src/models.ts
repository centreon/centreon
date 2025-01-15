export interface FormThreshold {
  baseColor?: string;
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}

export type ValueFormat = 'human' | 'raw';

export type SingleMetricGraphType = 'text' | 'gauge' | 'bar';
