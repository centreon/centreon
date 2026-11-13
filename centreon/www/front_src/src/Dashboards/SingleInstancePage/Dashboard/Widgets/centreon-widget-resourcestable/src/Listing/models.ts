import type { ListingMeta, ListingModel } from '@centreon/ui';

import type { SortOrder, Resource as WidgetResource } from '../../../models';

export enum DisplayType {
  All = 'all',
  Host = 'host',
  Service = 'service'
}

export enum ResourceType {
  host = 'host',
  service = 'service'
}

export type ResourceShortType = 'h' | 's' | 'm';

export interface NamedEntity {
  id: number;
  name: string;
  uuid?: string;
}

export interface Icon {
  id?: number;
  name: string;
  url: string;
}
export interface Severity {
  icon: Icon;
  id: number;
  level: number;
  name: string;
  type: string;
}

export interface Notes {
  label?: string;
  url: string;
}

export interface ResourceExternals {
  action_url?: string;
  notes?: Notes;
}
export interface ResourceEndpoints {
  acknowledgement?: string;
  check?: string;
  details?: string;
  downtime?: string;
  forced_check?: string;
  metrics?: string;
  performance_graph?: string;
  sensitivity?: string;
  status_graph?: string;
  timeline?: string;
  timeline_download?: string;
}

export interface ResourceUris {
  configuration?: string;
  logs?: string;
  reporting?: string;
}

export interface ResourceLinks {
  endpoints: ResourceEndpoints;
  externals: ResourceExternals;
  uris: ResourceUris;
}

export interface Status {
  name: string;
  severity_code: number;
}

export type Parent = Omit<Resource, 'parent'>;

export interface Resource extends NamedEntity {
  children?: unknown;
  duration?: string;
  has_active_checks_enabled?: boolean;
  has_passive_checks_enabled?: boolean;
  icon?: Icon;
  information?: string;
  is_acknowledged?: boolean;
  is_in_downtime?: boolean;
  is_notification_enabled?: boolean;
  last_check?: string;
  links?: ResourceLinks;
  parent?: Parent | null;
  service_id?: number;
  severity_level?: number;
  short_type: ResourceShortType;
  status?: Status;
  tries?: string;
  type: ResourceType;
}

export interface ResourceListingMeta extends ListingMeta {
  is_approximate?: boolean;
}

export interface ResourceListing extends Omit<ListingModel<Resource>, 'meta'> {
  meta: ResourceListingMeta;
}

export enum ResourceCategory {
  'anomaly-detection' = 'anomaly-detection',
  host = 'host',
  metaservice = 'metaservice',
  service = 'service'
}

// The columns handled by the CSV export endpoint
// (`ExportResourcesInput::EXPORT_ALLOWED_COLUMNS`). The ticket columns of the
// widget are not part of it.
export const exportableColumnIds = [
  'status',
  'resource',
  'parent_resource',
  'duration',
  'tries',
  'last_check',
  'information',
  'severity',
  'notes_url',
  'action_url',
  'state',
  'alias',
  'parent_alias',
  'fqdn',
  'monitoring_server_name'
];

export interface ExportParameters {
  columnIds: Array<string>;
  displayType: DisplayType;
  hostSeverities: Array<NamedEntity>;
  limit: number;
  page: number;
  resources: Array<WidgetResource>;
  serviceSeverities: Array<NamedEntity>;
  sortField?: string;
  sortOrder?: SortOrder;
  states: Array<string>;
  statusTypes: Array<'hard' | 'soft'>;
  statuses: Array<string>;
  visibleColumnIds: Array<string>;
}

export interface Ticket {
  hostID: number;
  serviceID?: number;
  ticketId?: number;
}
