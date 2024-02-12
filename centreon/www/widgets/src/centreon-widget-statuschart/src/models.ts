import { GlobalRefreshInterval, Resource } from '../../models';

export enum DisplayType {
  Donut = 'donut',
  Horizontal = 'horizontal',
  Pie = 'pie',
  Vertical = 'vertical'
}

interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  displayLegend: boolean;
  displayPredominentInformation: boolean;
  displayType: DisplayType;
  displayValues: boolean;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resourceType: Array<'host' | 'service'>;
  states: Array<string>;
  unit: 'number' | 'percentage';
}

export interface StatusChartProps {
  changeViewMode?: (displayType) => void;
  globalRefreshInterval: GlobalRefreshInterval;
  isFromPreview?: boolean;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
}
