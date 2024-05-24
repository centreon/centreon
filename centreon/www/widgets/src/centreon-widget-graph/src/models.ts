import { Metric, Resource } from '../../models';

export interface Data {
  metrics: Array<Metric>;
  resources: Array<Resource>;
}

export interface FormTimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

export interface PanelOptions {
  curveType: 'linear' | 'step' | 'natural';
  globalRefreshInterval?: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  threshold: FormThreshold;
  timeperiod: FormTimePeriod;
}

export interface FormThreshold {
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}
