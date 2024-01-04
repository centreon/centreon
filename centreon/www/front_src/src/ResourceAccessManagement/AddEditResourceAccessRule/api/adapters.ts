import { map, prop } from 'ramda';

export const adaptResourceAccessRule = ({
  contactGroups,
  contacts,
  data,
  description,
  isActivated,
  name
}): object => ({
  contact_groups: map(prop('id'), contactGroups),
  contacts: map(prop('id'), contacts),
  dataset_filters: data,
  description,
  is_enabled: isActivated,
  name
});
