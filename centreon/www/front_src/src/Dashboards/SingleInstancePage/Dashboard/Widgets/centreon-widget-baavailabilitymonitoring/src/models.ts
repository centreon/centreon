import { CommonWidgetProps, FormThreshold, Resource } from '../../models';

export interface Data {
  resources: Array<Resource>;
}

export interface FormTimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

interface PanelOptionsOld {
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

interface PanelOptions {
  globalRefreshInterval?: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  aggregationPeriod: 'daily' | 'monthly';
  reportingPeriod: '0' | '1' | '2' | '3' | '4' | '5';
  threshold: FormThreshold;
  displayType: 'line' | 'bar';
  curveType: 'linear' | 'step' | 'natural';
  showPoints: boolean;
  lineWidth?: number;
  lineWidthMode: 'auto' | 'custom';
  showArea: 'auto' | 'show' | 'hide';
  areaOpacity: number;
  lineStyleMode: 'solid' | 'dash' | 'dots';
  dashLength?: number;
  dashOffset?: number;
  dotOffset?: number;
  barOpacity: number;
  barRadius: number;
  showAxisBorder: boolean;
  yAxisTickLabelRotation: number;
  showGridLines: boolean;
  isCenteredZero: boolean;
  scale: 'linear' | 'logarithmic';
  scaleLogarithmicBase: string;
  boundariesType: 'auto' | 'custom';
  boundaries?: {
    min?: number;
    max?: number;
  };
}

export interface WidgetProps extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}
