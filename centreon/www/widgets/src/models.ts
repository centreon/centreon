import { SelectEntry } from '@centreon/ui';

export interface FormThreshold {
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}

export interface GlobalRefreshInterval {
  interval: number | null;
  type: 'global' | 'manual';
}

export interface Resource {
  resourceType: string;
  resources: Array<SelectEntry>;
}

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
