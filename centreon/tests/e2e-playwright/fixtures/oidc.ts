import type { ClapiAction } from '../helpers/CentreonApi';
import type { Credentials } from './credentials';

/**
 * OpenID Connect provider settings entered in the configuration form.
 *
 * `sso-proxy` is the keycloak reverse-proxy service of the docker compose
 * stack. The same host name is used by the browser (resolved to 127.0.0.1 via
 * the `--host-resolver-rules` launch arg of the `oidc` Playwright project) and
 * by the `web` container (resolved through docker DNS), so a single base URL
 * works for both the authorization redirect and the server-side token calls.
 */
export const oidcConfig = {
  authorizationEndpoint: '/auth',
  baseUrl: 'http://sso-proxy:8080/realms/Centreon_SSO/protocol/openid-connect',
  clientId: 'centreon-oidc-frontend',
  clientSecret: 'IKbUBottl5eoyhf0I5Io2nuDsTA85D50',
  introspectionTokenEndpoint: '/token/introspect',
  loginAttributePath: 'preferred_username',
  scopes: 'openid',
  tokenEndpoint: '/token'
};

/**
 * Non-admin user used for OIDC tests. The same login exists both as a local
 * Centreon contact (provisioned below, for local authentication) and as a
 * keycloak realm user (for the OIDC login flow).
 */
export const oidcUser: Credentials = {
  login: 'oidc',
  password: 'Centreon!2021'
};

/** ACL group/menu granting the OIDC user access to Home and Monitoring. */
export const providerAclActions: Array<ClapiAction> = [
  { action: 'ADD', object: 'ACLGROUP', values: 'ACL Group test;CYPRESS' },
  { action: 'ADD', object: 'ACLMENU', values: 'acl_menu_test;;' },
  {
    action: 'ADDMENU',
    object: 'ACLGROUP',
    values: 'ACL Group test;acl_menu_test'
  },
  {
    action: 'ADDRESOURCE',
    object: 'ACLGROUP',
    values: 'ACL Group test;All Resources'
  },
  {
    action: 'GRANTRW',
    object: 'ACLMENU',
    values: 'acl_menu_test;1;Monitoring'
  },
  { action: 'GRANTRW', object: 'ACLMENU', values: 'acl_menu_test;1;Home' },
  {
    action: 'ADDACTION',
    object: 'ACLGROUP',
    values: 'ACL Group test;Simple User'
  },
  { action: 'RELOAD', object: 'ACL', values: '' }
];

/** Local contact for the OIDC user, added to the ACL group above. */
export const oidcContactActions: Array<ClapiAction> = [
  {
    action: 'ADD',
    object: 'CONTACT',
    values: 'oidc;oidc;oidc@localhost;Centreon!2021;0;1;en_US;local'
  },
  { action: 'SETCONTACT', object: 'ACLGROUP', values: 'ACL Group test;oidc' }
];
