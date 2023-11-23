import { isNil } from 'ramda';

import { resourceAccessRulesEndpoint } from '../../../Listing/api/endpoints';

interface Props {
  id?: number | null;
}

export const resourceAccessRuleEndpoint = ({ id }: Props): string =>
  isNil(id)
    ? resourceAccessRulesEndpoint
    : `${resourceAccessRulesEndpoint}/${id}`;
