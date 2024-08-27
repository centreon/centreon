import { GraphOptionId } from '../Graph/Performance/models';
import {
  AcknowledgementDetails,
  Downtime,
  NamedEntity,
  Parent,
  ResourceLinks,
  ResourceShortType,
  ResourceType,
  Severity,
  Status
} from '../models';

import { CustomTimePeriod, TimePeriodId } from './tabs/Graph/models';

export interface Group extends Partial<NamedEntity> {
  configuration_uri: string | null;
}

export interface Category extends Partial<NamedEntity> {
  configuration_uri: string | null;
}

export interface Sensitivity {
  current_value: number;
  default_value: number;
  maximum_value: number;
  minimum_value: number;
}

export interface ResourceDetails extends NamedEntity {
  acknowledgement?: AcknowledgementDetails;
  alias?: string;
  calculation_type?: string;
  categories?: Array<Category>;
  command_line?: string;
  downtimes: Array<Downtime>;
  duration?: string;
  execution_time?: number;
  flapping?: boolean;
  fqdn?: string;
  groups?: Array<Group>;
  has_active_checks_enabled: boolean;
  has_passive_checks_enabled: boolean;
  information?: string;
  is_acknowledged: boolean;
  is_in_downtime: boolean;
  last_check?: string;
  last_notification?: string;
  last_status_change?: string;
  last_time_with_no_issue?: string;
  latency?: number;
  links?: ResourceLinks;
  monitoring_server_name?: string;
  next_check?: string;
  notification_number?: number;
  parent?: Parent;
  percent_state_change?: number;
  performance_data?: string;
  sensitivity?: Sensitivity;
  service_id?: number;
  severity?: Severity;
  severity_level?: number;
  short_type?: ResourceShortType;
  status?: Status;
  timezone?: string;
  tries?: string;
  type: ResourceType;
  uuid: string;
}

export interface ResourceDetailsAtom {
  parentResourceId?: number;
  parentResourceType?: string;
  resourceId?: number;
  resourcesDetailsEndpoint?: string;
}

export interface GraphOption {
  id: GraphOptionId;
  label: string;
  value: boolean;
}

export interface GraphOptions {
  [GraphOptionId.displayEvents]: GraphOption;
}

export interface GraphTabParameters {
  options?: GraphOptions;
}

export interface ServicesTabParameters {
  options: GraphOptions;
}

export interface TabParameters {
  graph?: GraphTabParameters;
  services?: ServicesTabParameters;
}

export interface DetailsUrlQueryParameters {
  customTimePeriod?: CustomTimePeriod;
  id: number;
  parentId?: number;
  parentType?: string;
  resourcesDetailsEndpoint?: string;
  selectedTimePeriodId?: TimePeriodId;
  tab?: string;
  tabParameters?: TabParameters;
  type?: string;
  uuid: string;
}
