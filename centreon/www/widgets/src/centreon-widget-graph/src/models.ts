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
  areaOpacity: number;
  barOpacity: number;
  barRadius: number;
  curveType: 'linear' | 'step' | 'natural';
  dashLength?: number;
  dashOffset?: number;
  displayType: 'line' | 'bar' | 'bar-stacked';
  dotOffset?: number;
  globalRefreshInterval?: number;
  gridLinesType: 'horizontal' | 'vertical' | 'all';
  isCenteredZero: boolean;
  legendDisplayMode: 'grid' | 'list';
  legendPlacement: 'right' | 'bottom' | 'left';
  lineStyleMode: 'solid' | 'dash' | 'dots';
  lineWidth?: number;
  lineWidthMode: 'auto' | 'custom';
  orientation: 'auto' | 'horizontal' | 'vertical';
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  scale: 'linear' | 'logarithmic';
  scaleLogarithmicBase: string;
  showArea: 'auto' | 'show' | 'hide';
  showAxisBorder: boolean;
  showGridLines: boolean;
  showLegend: boolean;
  showPoints: boolean;
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
