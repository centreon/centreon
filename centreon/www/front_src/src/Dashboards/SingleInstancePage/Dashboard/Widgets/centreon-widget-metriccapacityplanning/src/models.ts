import { CommonWidgetProps, Metric, Resource } from '../../models';

export interface Data {
  metrics: Array<Metric>;
  resources: Array<Resource>;
}

export interface FormTimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

interface PanelOptions {
  areaOpacity: number;
  barOpacity: number;
  barRadius: number;
  curveType: 'linear' | 'step' | 'natural';
  dashLength?: number;
  dashOffset?: number;
  displayType: 'line' | 'bar';
  dotOffset?: number;
  globalRefreshInterval?: number;
  gridLinesType: 'horizontal' | 'vertical' | 'all';
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
  timePeriod: string;
  nbDays: number;
  yAxisTickLabelRotation: number;
  showThresholds: boolean;
}
export interface WidgetProps extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}
