import type { ClapiAction } from '../helpers/CentreonApi';
import type { Credentials } from './credentials';

/** Password of the restricted users the ACL tests create. */
const password = 'Centreon!2021User';

export interface RestrictedUserOptions {
  /** Contact parameters, such as `reach_api` for the API v2 pages. */
  contactParams?: Record<string, string>;
  /** Hosts its resource access grants. */
  hosts?: Array<string>;
  /** Host categories its resource access grants. */
  hostCategories?: Array<string>;
  /** Menu entries it can reach, read-write or read-only. */
  menus: Array<{ access: 'ro' | 'rw'; topology: string }>;
  /** Unique name of the user and of its ACL objects. */
  name: string;
}

export interface RestrictedUser {
  credentials: Credentials;
  provisioning: Array<ClapiAction>;
  teardown: Array<ClapiAction>;
}

/**
 * A non-admin user whose access group carries one menu access and one
 * resource access, then the ACL reload that makes them effective: the pages
 * read the computed `centreon_acl`, not the configuration.
 */
export const restrictedUser = ({
  contactParams = {},
  hostCategories = [],
  hosts = [],
  menus,
  name
}: RestrictedUserOptions): RestrictedUser => {
  const group = `${name}-group`;
  const menu = `${name}-menu`;
  const resource = `${name}-resource`;
  const action = (
    object: string,
    verb: string,
    values: string
  ): ClapiAction => ({ action: verb, object, values });

  return {
    credentials: { login: name, password },
    provisioning: [
      action(
        'CONTACT',
        'ADD',
        `${name};${name};${name}@centreon.test;${password};0;1;en_US;local`
      ),
      ...Object.entries(contactParams).map(([param, value]) =>
        action('CONTACT', 'SETPARAM', `${name};${param};${value}`)
      ),
      action('ACLGROUP', 'ADD', `${group};${group}`),
      action('ACLGROUP', 'SETCONTACT', `${group};${name}`),
      action('ACLMENU', 'ADD', `${menu};${menu}`),
      ...menus.map(({ access, topology }) =>
        action(
          'ACLMENU',
          access === 'rw' ? 'GRANTRW' : 'GRANTRO',
          `${menu};1;${topology}`
        )
      ),
      action('ACLGROUP', 'ADDMENU', `${group};${menu}`),
      action('ACLRESOURCE', 'ADD', `${resource};${resource}`),
      action('ACLGROUP', 'ADDRESOURCE', `${group};${resource}`),
      ...hosts.map((host) =>
        action('ACLRESOURCE', 'GRANT_HOST', `${resource};${host}`)
      ),
      ...hostCategories.map((category) =>
        action(
          'ACLRESOURCE',
          'ADDFILTER_HOSTCATEGORY',
          `${resource};${category}`
        )
      ),
      action('ACL', 'RELOAD', '')
    ],
    teardown: [
      action('CONTACT', 'DEL', name),
      action('ACLGROUP', 'DEL', group),
      action('ACLMENU', 'DEL', menu),
      action('ACLRESOURCE', 'DEL', resource)
    ]
  };
};
