import { ResourceAccessRule } from '../../models';

export const getEmptyInitialValues = (): ResourceAccessRule => ({
  contactGroups: [],
  contacts: [],
  datasetFilters: [[{ resourceType: undefined, resources: [] }]],
  description: '',
  isActivated: true,
  name: ''
});

export const getInitialValues = ({
  contactGroups,
  contacts,
  datasetFilters,
  description,
  isActivated,
  name
}): ResourceAccessRule => ({
  contactGroups,
  contacts,
  datasetFilters,
  description,
  isActivated,
  name
});
