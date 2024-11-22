import { prop, toLower } from 'ramda';

import {
  CriteriaById,
  CriteriaNames,
  selectableResourceTypes,
  selectableStateTypes,
  selectableStates,
  selectableStatuses
} from '../models';

export interface CriteriaId {
  id: string;
}

export interface CriteriaValueSuggestionsProps {
  criterias: Array<CriteriaId>;
  selectedValues: Array<string>;
}

export const criteriaNameSortOrder = {
  [CriteriaNames.hostGroups]: 5,
  [CriteriaNames.monitoringServers]: 7,
  [CriteriaNames.resourceTypes]: 1,
  [CriteriaNames.serviceGroups]: 6,
  [CriteriaNames.states]: 2,
  [CriteriaNames.statuses]: 3,
  [CriteriaNames.statusTypes]: 4,
  [CriteriaNames.serviceCategories]: 9,
  [CriteriaNames.hostCategories]: 8,
  [CriteriaNames.hostSeverities]: 10,
  [CriteriaNames.hostSeverityLevels]: 11,
  [CriteriaNames.serviceSeverities]: 12,
  [CriteriaNames.serviceSeverityLevels]: 13
};

export interface AutocompleteSuggestionProps {
  cursorPosition: number;
  newSelectableCriterias?: CriteriaById;
  search: string;
}

export enum SearchableFields {
  alias = 'alias',
  fqdn = 'fqdn',
  'h.address' = 'h.address',
  'h.alias' = 'h.alias',
  'h.name' = 'h.name',
  information = 'information',
  name = 'name',
  parent_alias = 'parent_alias',
  parent_name = 'parent_name',
  's.description' = 's.description'
}

export const searchableFields = [
  'h.name',
  'h.alias',
  'h.address',
  's.description',
  'alias',
  'parent_alias',
  'fqdn',
  'information'
];

const statusNameToQueryLanguageName = selectableStatuses
  .map(prop('id'))
  .reduce((previous, current) => {
    return { ...previous, [current]: toLower(current) };
  }, {});

export const criteriaNameToQueryLanguageName = {
  ...statusNameToQueryLanguageName,
  resource_type: 'type',
  unhandled_problems: 'unhandled'
};

const staticCriteriaValuesByName = {
  resource_type: selectableResourceTypes,
  state: selectableStates,
  status: selectableStatuses,
  status_type: selectableStateTypes
};

export const dynamicCriteriaValuesByName = [
  CriteriaNames.hostGroups,
  CriteriaNames.monitoringServers,
  CriteriaNames.serviceGroups,
  CriteriaNames.serviceCategories,
  CriteriaNames.hostCategories,
  CriteriaNames.hostSeverities,
  CriteriaNames.serviceSeverities,
  CriteriaNames.hostSeverityLevels,
  CriteriaNames.serviceSeverityLevels,
  CriteriaNames.names,
  CriteriaNames.parentNames
];

export const getSelectableCriteriasByName = (
  name: string
): Array<{ id: string; name: string }> => {
  return staticCriteriaValuesByName[name];
};

export const staticCriteriaNames = Object.keys(staticCriteriaValuesByName);
