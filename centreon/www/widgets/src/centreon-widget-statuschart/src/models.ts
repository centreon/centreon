import { GlobalRefreshInterval, Resource } from '../../models';

export enum DisplayType {
  Donut = 'donut',
  Horizontal = 'horizontal',
  Pie = 'pie',
  Vertical = 'vertical'
}

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  displayLegend: boolean;
  displayType: DisplayType;
  displayValues: boolean;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resourceTypes: Array<'host' | 'service'>;
  unit: 'number' | 'percentage';
}

export interface StatusChartProps {
  globalRefreshInterval: GlobalRefreshInterval;
  isFromPreview?: boolean;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
}

export interface ChartType {
  displayLegend: boolean;
  displayType: DisplayType;
  displayValues: boolean;
  getLinkToResourceStatusPage: (resourcesType, status) => string;
  labelNoDataFound: string;
  refreshCount: number;
  refreshIntervalToUse: number | false;
  resourceType: 'host' | 'service';
  resourceTypes: Array<'host' | 'service'>;
  resources: Array<Resource>;
  title?: string;
  unit: 'number' | 'percentage';
}

type StatusDetail = {
  acknowledged: number;
  in_downtime: number;
  total: number;
};

type Status =
  | 'critical'
  | 'warning'
  | 'unknown'
  | 'pending'
  | 'ok'
  | 'down'
  | 'unreachable'
  | 'up';

export type StatusType = {
  [key in Status]?: StatusDetail;
} & {
  total: number;
};
