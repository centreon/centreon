export const getEmptyInitialValues = (): object => ({
  contactGroups: [],
  contacts: [],
  data: { datasetFilters: [{ resourceType: '', resources: [] }] },
  description: '',
  isActivated: true,
  name: ''
});

export const getInitialValues = ({
  contactGroups,
  contacts,
  data,
  description,
  isActivated,
  name
}): object => ({
  contactGroups,
  contacts,
  data,
  description,
  isActivated,
  name
});
