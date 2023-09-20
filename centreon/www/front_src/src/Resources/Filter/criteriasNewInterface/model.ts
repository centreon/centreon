import { ResourceType } from '../../models';
import {
  labelPending,
  labelUp,
  labelDown,
  labelUnreachable,
  labelOk,
  labelWarning,
  labelCritical,
  labelUnknown
} from '../../translatedLabels';
import { CriteriaNames } from '../Criterias/models';

// todo add period time filter when it's implemented
export enum BasicCriteria {
  hostGroups = CriteriaNames.hostGroups,
  resourceTypes = CriteriaNames.resourceTypes,
  serviceGroups = CriteriaNames.serviceGroups,
  states = CriteriaNames.states,
  statues = CriteriaNames.statuses,
  monitoringServers = CriteriaNames.monitoringServers
}

export enum ExtendedCriteria {
  hostSeverities = CriteriaNames.hostSeverities,
  hostCategories = CriteriaNames.hostCategories,
  serviceSeverities = CriteriaNames.serviceSeverities,
  serviceCategories = CriteriaNames.serviceCategories,
  resourceTypes = CriteriaNames.resourceTypes,
  statusTypes = CriteriaNames.statusTypes,
  serviceSeverityLevels = CriteriaNames.serviceSeverityLevels,
  hostSeverityLevels = CriteriaNames.hostSeverityLevels
}
export enum categoryHostStatus {
  UP = labelUp,
  DOWN = labelDown,
  PENDING = labelPending,
  UNREACHABLE = labelUnreachable
}

export enum categoryServiceStatus {
  OK = labelOk,
  WARNING = labelWarning,
  CRITICAL = labelCritical,
  UNKNOWN = labelUnknown,
  PENDING = labelPending
}

export enum BasicCriteriaResourceType {
  host = ResourceType.host,
  service = ResourceType.service
}

export enum ExtendedCriteriaResourceType {
  anomalyDetection = ResourceType.anomalyDetection,
  metaservice = ResourceType.metaservice
}

export enum CategoryFilter {
  BasicFilter = 'BasicFilter',
  ExtendedFilter = 'ExtendedFilter'
}
export enum SectionType {
  host = 'host',
  service = 'service'
}

export interface MergeArraysByField {
  firstArray: Array<Record<string, unknown>>;
  mergeBy: string;
  secondArray: Array<Record<string, unknown>>;
}
