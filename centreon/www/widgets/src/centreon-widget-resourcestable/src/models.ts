import { GlobalRefreshInterval, Resource } from '../../models';

import { DisplayType, SortOrder } from './Listing/models';

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  displayType: DisplayType;
  limit?: number;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  selectedColumnIds?: Array<string>;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statuses: Array<string>;
}

export interface ResourcesTableProps {
  globalRefreshInterval: GlobalRefreshInterval;
  panelData: Data;
  panelOptions: PanelOptions;
  refreshCount: number;
  setPanelOptions?: (panelOptions: PanelOptions) => void;
}
