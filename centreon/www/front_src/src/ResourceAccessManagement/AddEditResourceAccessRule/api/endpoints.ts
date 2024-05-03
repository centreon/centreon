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

export const findHostGroupsEndpoint = `${baseEndpoint}/configuration/hosts/groups`;
export const findHostCategoriesEndpoint = `${baseEndpoint}/configuration/hosts/categories`;
export const findHostsEndpoint = `${baseEndpoint}/configuration/hosts`;
export const findServiceGroupsEndpoint = `${baseEndpoint}/configuration/services/groups`;
export const findServiceCategoriesEndpoint = `${baseEndpoint}/configuration/services/categories`;
export const findServicesEndpoint = `${baseEndpoint}/configuration/services`;
export const findMetaServicesEndpoint = `${baseEndpoint}/configuration/metaservices`;

export const findBusinessViewsEndpoint = `${baseEndpoint}/bam/configuration/business-views`;
