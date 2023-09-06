export interface NamedEntity {
  id: number;
  name: string;
}

export interface Metric extends NamedEntity {
  unit: string;
}

export interface ServiceMetric extends NamedEntity {
  metrics: Array<Metric>;
}

export interface Data {
  metrics: Array<ServiceMetric>;
}

export interface FormThreshold {
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}

export type ValueFormat = 'human' | 'raw';
