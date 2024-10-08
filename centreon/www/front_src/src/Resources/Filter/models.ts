import { isNil } from 'ramda';

import {
  labelAll,
  labelAllAlerts,
  labelNewFilter,
  labelUnhandledAlerts
} from '../translatedLabels';

import getDefaultCriterias from './Criterias/default';
import {
  Criteria,
  criticalStatus,
  downStatus,
  hardStateType,
  selectableResourceTypes,
  selectableStates,
  selectableStatuses,
  unhandledState,
  unknownStatus,
  warningStatus
} from './Criterias/models';

export interface Filter {
  criterias: Array<Criteria>;
  id: number | string;
  name: string;
}

const allFilter = {
  criterias: getDefaultCriterias(),
  id: 'all',
  name: labelAll
};

const newFilter = {
  id: '',
  name: labelNewFilter
} as Filter;

const unhandledProblemsFilter: Filter = {
  criterias: getDefaultCriterias({
    states: [unhandledState],
    statusTypes: [hardStateType],
    statuses: [warningStatus, downStatus, criticalStatus, unknownStatus]
  }),
  id: 'unhandled_problems',
  name: labelUnhandledAlerts
};

const resourceProblemsFilter: Filter = {
  criterias: getDefaultCriterias({
    statuses: [warningStatus, downStatus, criticalStatus, unknownStatus]
  }),
  id: 'resource_problems',
  name: labelAllAlerts
};

const standardFilterById = {
  all: allFilter,
  resource_problems: resourceProblemsFilter,
  unhandled_problems: unhandledProblemsFilter
};

const isCustom = ({ id }: Filter): boolean => {
  return isNil(standardFilterById[id]);
};

export {
  allFilter,
  unhandledProblemsFilter,
  resourceProblemsFilter,
  newFilter,
  selectableResourceTypes,
  selectableStates,
  selectableStatuses,
  standardFilterById,
  isCustom
};
