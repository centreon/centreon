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
  gridLinesType: 'horizontal' | 'vertical' | 'all';
  isCenteredZero: boolean;
  legendDisplayMode: 'grid' | 'list';
  legendPlacement: 'right' | 'bottom' | 'left';
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  scale: 'linear' | 'logarithimc';
  scaleLogarithmicBase: string;
  showAxisBorder: boolean;
  showGridLines: boolean;
  showLegend: boolean;
  threshold: FormThreshold;
  timeperiod: FormTimePeriod;
  tooltipMode: 'all' | 'single' | 'hidden';
  tooltipSortOrder: 'name' | 'ascending' | 'descending';
  yAxisTickLabelRotation: number;
}

export interface FormThreshold {
  criticalType: 'default' | 'custom';
  customCritical: number;
  customWarning: number;
  enabled: boolean;
  warningType: 'default' | 'custom';
}
