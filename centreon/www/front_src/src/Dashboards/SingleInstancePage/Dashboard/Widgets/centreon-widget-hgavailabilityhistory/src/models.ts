import { CommonWidgetProps, FormThreshold, Resource } from '../../models';

export interface Data {
  resources: Array<Resource>;
}

export interface FormTimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
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
  showLegend: boolean;
  legendPlacement: 'left' | 'bottom' | 'right';
  tooltipMode: 'all' | 'single' | 'hidden';
}

export interface WidgetProps extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}
