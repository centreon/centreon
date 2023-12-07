import { isNil } from 'ramda';

import { baseEndpoint } from '../../../api/endpoint';
import { resourceAccessRulesEndpoint } from '../../Listing/api/endpoints';

interface Props {
  id?: number | null;
}

export const resourceAccessRuleEndpoint = ({ id }: Props): string =>
  isNil(id)
    ? resourceAccessRulesEndpoint
    : `${resourceAccessRulesEndpoint}/${id}`;

export const findContactGroupsEndpoint = `${baseEndpoint}/configuration/contacts/groups`;
export const findContactsEndpoint = `${baseEndpoint}/configuration/users`;
