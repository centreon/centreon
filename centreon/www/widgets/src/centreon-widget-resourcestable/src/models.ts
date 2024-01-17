import { GlobalRefreshInterval, Resource as DataResource } from '../../models';

export interface Data {
  resources: Array<DataResource>;
}

export interface PanelOptions {
  displayType: string;
  limit?: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  selectedColumnIds?: Array<string>;
  sortField?: string;
  sortOrder?: string;
  states: Array<string>;
  statuses: Array<string>;
}

export interface ResourcesTableProps {
  globalRefreshInterval: GlobalRefreshInterval;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
  setPanelOptions: (panelOptions: PanelOptions) => void;
}
