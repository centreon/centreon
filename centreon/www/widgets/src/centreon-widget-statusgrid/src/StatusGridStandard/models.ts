import { CommonWidgetProps, Resource, SeverityStatus } from '../../../models';

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resourceType: string;
  sortBy: string;
  states: Array<string>;
  statuses: Array<SeverityStatus>;
  tiles: number;
  viewMode: 'standard' | 'condensed';
}

export interface StatusGridProps extends CommonWidgetProps<PanelOptions> {
  panelData: Data;
  panelOptions: PanelOptions;
}

interface Icon {
  id?: number;
  name: string;
  url: string;
}

enum ResourceType {
  host = 'host',
  service = 'service'
}

interface ResourceEndpoints {
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

interface ResourceLinks {
  endpoints: ResourceEndpoints;
}

type Parent = Omit<ResourceStatus, 'parent'>;

interface Status {
  name: string;
  severity_code: number;
}

export interface ResourceStatus {
  duration?: string;
  has_active_checks_enabled?: boolean;
  has_passive_checks_enabled?: boolean;
  icon?: Icon;
  id: number;
  information?: string;
  is_acknowledged?: boolean;
  is_in_downtime?: boolean;
  is_notification_enabled?: boolean;
  last_check?: string;
  links?: ResourceLinks;
  name: string;
  parent?: Parent | null;
  service_id?: number;
  severity_level?: number;
  status?: Status;
  tries?: string;
  type: ResourceType;
  uuid: string;
}

export interface ResourceData {
  acknowledgementEndpoint?: string;
  downtimeEndpoint?: string;
  information?: string;
  is_acknowledged?: boolean;
  is_in_downtime?: boolean;
  metricsEndpoint?: string;
  name: string;
  parentName?: string;
  parentStatus?: number;
  status?: number;
  statusName?: string;
}

export interface MetricProps {
  criticalHighThreshold: number | null;
  criticalLowThreshold: number | null;
  currentValue: number | null;
  id: number;
  name: string;
  unit: string;
  warningHighThreshold: number | null;
  warningLowThreshold: number | null;
}
