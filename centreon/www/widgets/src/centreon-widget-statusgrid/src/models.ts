import { FormThreshold, FormTimePeriod, Resource } from '../../models';

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  globalRefreshInterval?: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  threshold: FormThreshold;
  timeperiod: FormTimePeriod;
}
