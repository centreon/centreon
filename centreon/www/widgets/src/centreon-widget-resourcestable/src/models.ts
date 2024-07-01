import { CommonWidgetProps, Resource, SortOrder } from '../../models';

import { DisplayType } from './Listing/models';

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
  statusTypes: Array<'soft' | 'hard'>;
  statuses: Array<string>;
}

export interface ResourcesTableProps extends CommonWidgetProps<PanelOptions> {
  changeViewMode?: (displayType) => void;
  panelData: Data;
  panelOptions: PanelOptions;
}
