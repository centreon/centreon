import { NewMetric, Resource } from '../../models';

export interface Data {
  metrics: Array<NewMetric>;
  resources: Array<Resource>;
}

export interface FormThreshold {
  baseColor?: string;
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}

export type ValueFormat = 'human' | 'raw';
