import { SeverityCode } from '@centreon/ui';

import { CommonWidgetProps, Data, NamedEntity, SortOrder } from '../../models';

export interface PanelOptions {
  limit?: number;
  page?: number;
  refreshInterval: 'default' | 'custom';
  refreshIntervalCustom?: number;
  resourceTypes: Array<string>;
  sortField?: string;
  sortOrder?: SortOrder;
  statuses: Array<string>;
}

export interface WidgetProps extends CommonWidgetProps<PanelOptions> {
  panelData: Pick<Data, 'resources'>;
  panelOptions: PanelOptions;
}

export interface Service {
  description: string;
  displayName: string;
  id: number;
  status: SeverityCode;
}

export interface Host extends NamedEntity {
  alias: string;
  displayName: string;
  services: Array<Service>;
  status: SeverityCode;
}

export interface Group extends NamedEntity {
  hosts: Array<Host>;
}

export interface FormattedGroup extends NamedEntity {
  hosts: Array<Host>;
  statuses: Array<string>;
}

export interface RowProps {
  groupType: string;
  isFromPreview?: boolean;
  row: FormattedGroup;
}
