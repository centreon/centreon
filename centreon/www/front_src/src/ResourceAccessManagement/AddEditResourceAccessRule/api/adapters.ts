import { map, prop } from 'ramda';

export const adaptResourceAccessRule = ({
  contactGroups,
  contacts,
  description,
  isActivated,
  name
}): object => ({
  contact_groups: map(prop('id'), contactGroups),
  contacts: map(prop('id'), contacts),
  description,
  is_enabled: isActivated,
  name
});
