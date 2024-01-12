import { GlobalRefreshInterval, Resource as DataResource } from '../../models';

export interface Data {
  resources: Array<DataResource>;
}

export interface PanelOptions {
  displayType: string;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  states: Array<string>;
  statuses: Array<string>;
}

export interface ResourcesTableProps {
  globalRefreshInterval: GlobalRefreshInterval;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
}
