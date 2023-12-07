export const getEmptyInitialValues = (): object => ({
  contactGroups: [],
  contacts: [],
  description: '',
  isActivated: true,
  name: ''
});

export const getInitialValues = ({
  contactGroups,
  contacts,
  description,
  isActivated,
  name
}): object => ({
  contactGroups,
  contacts,
  description,
  isActivated,
  name
});
