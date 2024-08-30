import {
  CommonWidgetProps,
  NamedEntity,
  Resource,
  SortOrder
} from '../../models';

import { DisplayType } from './Listing/models';

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  displayResources: 'all' | 'withTicket' | 'withoutTicket';
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  isDownHostHidden: boolean;
  isOpenTicketEnabled: boolean;
  isUnreachableHostHidden: boolean;
  limit?: number;
  provider?: { id: number; name: string };
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  selectedColumnIds?: Array<string>;
  serviceSeverities: Array<NamedEntity>;
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
