import type { ListingModel } from '@centreon/ui';

export enum ResourceType {
  anomalyDetection = 'anomaly-detection',
  host = 'host',
  metaservice = 'metaservice',
  service = 'service'
}

export enum ResourceCategory {
  'anomaly-detection' = 'service',
  service = 'service',
  host = 'host',
  metaservice = 'metaservice'
}

export type ResourceShortType = 'h' | 's' | 'm' | 'a';

export interface NamedEntity {
  id: number;
  name: string;
  uuid: string;
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

export type Parent = Omit<Resource, 'parent' | 'uuid'>;
export interface Status {
  name: string;
  severity_code: number;
}

export interface Resource extends NamedEntity {
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
  short_type?: ResourceShortType;
  status?: Status;
  tries?: string;
  type: ResourceType;
}

export type ResourceListing = ListingModel<Resource>;

export interface Downtime {
  author_name: string;
  comment: string;
  end_time: string;
  entry_time: string;
  start_time: string;
}

export interface Acknowledgement {
  author_name: string;
  comment: string;
  entry_time: string;
  is_persistent: boolean;
  is_sticky: boolean;
}

export interface ResourceEndpoints {
  acknowledgement?: string;
  check?: string;
  details?: string;
  downtime?: string;
  forced_check?: string;
  metrics?: string;
  notification_policy?: string;
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

export interface Notes {
  label?: string;
  url: string;
}

export interface ResourceExternals {
  action_url?: string;
  notes?: Notes;
}

export interface ResourceLinks {
  endpoints: ResourceEndpoints;
  externals?: ResourceExternals;
  uris: ResourceUris;
}

export type TranslationType = (label: string) => string;

export enum SortOrder {
  asc = 'asc',
  desc = 'desc'
}

export enum Visualization {
  All = 'All',
  Host = 'By host',
  Service = 'By service'
}

export interface AcknowledgementDetails {
  author_id: number;
  author_name: string;
  comment: string;
  deletion_time: string;
  entry_time: string;
  host_id: number;
  id: number;
  is_notify_contacts: boolean;
  is_persistent_comment: boolean;
  is_sticky: boolean;
  poller_id: number;
  service_id: number;
  state: number;
}
