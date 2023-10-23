import { SelectEntry } from '@centreon/ui';

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
import {
  Criteria,
  CriteriaById,
  CriteriaDisplayProps,
  CriteriaNames,
  SearchDataPropsCriterias
} from '../Criterias/models';

export enum BasicCriteria {
  hostGroups = CriteriaNames.hostGroups,
  resourceTypes = CriteriaNames.resourceTypes,
  serviceGroups = CriteriaNames.serviceGroups,
  states = CriteriaNames.states,
  statues = CriteriaNames.statuses,
  monitoringServers = CriteriaNames.monitoringServers,
  names = CriteriaNames.names,
  parentNames = CriteriaNames.parentNames
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

export type MergeArraysByField = {
  firstArray: Array<Record<string, unknown>>;
  mergeBy: string;
  secondArray: Array<Record<string, unknown>>;
};

export interface DataFilter {
  builtCriteria: CriteriaDisplayProps;
  selectableCriteria: Array<Criteria>;
}
export interface BuildDataByCategoryFilter {
  CriteriaType: Array<BasicCriteria> | Array<ExtendedCriteria>;
  builtCriteria: CriteriaById;
  selectableCriteria: Array<Criteria>;
}

export interface DataByCategoryFilter {
  builtCriteria: CriteriaById;
  categoryFilter: CategoryFilter;
  selectableCriteria: Array<Criteria>;
}

export interface Data {
  newSelectableCriterias: CriteriaById;
  searchData: SearchDataPropsCriterias;
  selectableCriterias: Array<Criteria>;
}

export interface ChangedCriteriaParams {
  filterName: string;
  updatedValue: unknown;
}

export interface MemoizedChild {
  basicData: Array<Criteria & CriteriaDisplayProps>;
  changeCriteria: (data: ChangedCriteriaParams) => void;
  searchData?: SearchDataPropsCriterias;
}

export interface MemoizedChildSectionWrapper extends MemoizedChild {
  filterName: string;
  searchData?: SearchDataPropsCriterias;
  sectionType: SectionType;
}

export interface DeactivateProps {
  isDeactivated?: boolean;
}

export interface FindData {
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  findBy?: string;
}

export interface ParametersRemoveDuplicate {
  array: Array<Record<string, unknown>>;
  byFields: Array<string>;
}

export interface SelectedResourceType extends SelectEntry {
  checked: boolean;
  resourceType: ResourceType | SectionType;
}

export interface FieldInformationFromSearchInput {
  content: string;
  fieldInformation: string | undefined;
}
export interface ParametersFieldInformation {
  field: string;
  search: string;
}

export interface HandleDataByCategoryFilter {
  data: Array<Criteria & CriteriaDisplayProps>;
  fieldToUpdate: string;
  filter: CategoryFilter | SectionType | ResourceType;
}

export interface CallbackCheck {
  dataToCheck: Array<string>;
  id: string;
}
