import type {
  CommonWidgetProps,
  NamedEntity,
  Resource,
  SortOrder
} from '../../models';
import type { DisplayType } from './Listing/models';

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  displayResources: 'withTicket' | 'withoutTicket';
  displayType: DisplayType;
  enableHostTicketCreation: boolean;
  enableServiceTicketCreation: boolean;
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

export interface OpenTicketContext {
  displayResources: 'withTicket' | 'withoutTicket';
  isDownHostHidden: boolean;
  isOpenTicketInstalled: boolean;
  isOpenTicketEnabled: boolean;
  isUnreachableHostHidden: boolean;
  enableHostTicketCreation: boolean;
  enableServiceTicketCreation: boolean;
  provider?: { id: number; name: string };
}
