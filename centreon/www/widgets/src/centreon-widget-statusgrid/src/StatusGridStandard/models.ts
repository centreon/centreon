import { CommonWidgetProps, Resource, SeverityStatus } from '../../../models';

export interface Data {
  resources: Array<Resource>;
}

export interface PanelOptions {
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
  resourceType?: string;
  sortBy: string;
  states?: Array<string>;
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
  status?: {
    name: string;
    severity_code: number;
  };
  tries?: string;
  type: ResourceType;
  uuid: string;
}

export interface ResourceData {
  acknowledgementEndpoint?: string;
  businessActivity?: string | null;
  downtimeEndpoint?: string;
  id?: number;
  information?: string;
  is_acknowledged?: boolean;
  is_in_downtime?: boolean;
  metricsEndpoint?: string;
  name: string;
  parentId?: string;
  parentName?: string;
  parentStatus?: number;
  resourceId?: number;
  status?: number;
  statusName?: string;
  type?: string;
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

export enum IndicatorType {
  AnomalyDetection = 'anomaly-detection',
  BooleanRule = 'boolean-rule',
  BusinessActivity = 'business-activity',
  Host = 'host',
  MetaService = 'meta-service',
  Service = 'service'
}

export enum CalculationMethodType {
  BestStatus = 'Best Status',
  Impact = 'Impact',
  Ratio = 'Ratio',
  WorstStatus = 'Worst Status'
}

export interface Status {
  code: number;
  name: string;
  severityCode: number;
}

export interface Impact {
  critical: number | null;
  unknown: number | null;
  warning: number | null;
}

export interface IndicatorResource {
  id: number;
  name: string;
  parentId: number | null;
  parentName: string | null;
}

export interface Indicator {
  id: number;
  impact: Impact | null;
  name: string;
  resource: IndicatorResource | null;
  status: Status;
  type: string;
}

export interface CalculationMethod {
  criticalThreshold: number | null;
  id: number;
  isPercentage: boolean | null;
  name: string;
  warningThreshold: number | null;
}

export interface BusinessActivity {
  calculationMethod: CalculationMethod;
  id: number;
  indicators: Array<Indicator>;
  infrastructureView: string | null;
  name: string;
  status: Status;
}

export interface BooleanRule {
  expressionStatus: boolean;
  id: number;
  isImpactingWhenTrue: boolean;
  name: string;
  status: Status;
}
