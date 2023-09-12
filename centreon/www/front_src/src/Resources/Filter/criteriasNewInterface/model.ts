import { CriteriaNames } from '../Criterias/models';

// todo add period time filter whern it's implemented
export interface BasicCriteria {
  hostGroups: CriteriaNames.hostGroups;
  resourceTypes: CriteriaNames.resourceTypes;
  serviceGroups: CriteriaNames.serviceGroups;
  states: CriteriaNames.states;
  statues: CriteriaNames.statuses;
}
