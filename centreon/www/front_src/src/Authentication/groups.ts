import { Group } from '@centreon/ui';

import { labelRolesMapping } from './Openid/translatedLabels';
import {
  labelActivation,
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelClientAddresses,
  labelGroupsMapping,
  labelIdentityProvider
} from './translatedLabels';

export const groups: Array<Group> = [
  {
    name: labelActivation,
    order: 1
  },
  {
    name: labelIdentityProvider,
    order: 2
  },
  {
    name: labelAuthenticationConditions,
    order: 3
  },
  {
    name: labelClientAddresses,
    order: 4
  },
  {
    name: labelAutoImportUsers,
    order: 5
  },
  {
    name: labelRolesMapping,
    order: 6
  },
  {
    name: labelGroupsMapping,
    order: 7
  }
];
