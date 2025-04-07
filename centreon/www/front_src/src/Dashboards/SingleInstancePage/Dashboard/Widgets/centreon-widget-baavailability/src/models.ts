import { CommonWidgetProps, Resource } from '../../models';

export interface Data {
  resources: Array<Resource>;
}

export interface FormTimePeriod {
  end?: string | null;
  start?: string | null;
  timePeriodType: number;
}

interface PanelOptions {
  displayType: 'text' | 'gauge' | 'bar';
  reportingPeriod: number;
  showThresholds: boolean;
  showLegend: boolean;
  legendPosition: 'left' | 'bottom' | 'right';
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
}
export interface WidgetProps extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}
