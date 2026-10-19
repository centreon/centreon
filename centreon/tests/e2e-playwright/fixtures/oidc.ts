import type { CentreonApi } from '../helpers/CentreonApi';
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
 * The only end user of the `Centreon_SSO` realm: role `centreon-editor`,
 * groups `/Notification` and `/Supervisors`, name "OpenId Connect OIDC".
 */
export const oidcUser: Credentials = {
  login: 'oidc',
  password: 'Centreon!2021'
};

export const oidcUserClaims = {
  email: 'oidc@localhost',
  group: '/Supervisors',
  name: 'OpenId Connect OIDC',
  role: 'centreon-editor'
};

const providerPath = '/administration/authentication/providers/openid';

const disabledMapping = {
  attribute_path: '',
  endpoint: { custom_endpoint: null, type: 'introspection_endpoint' },
  is_enabled: false
};

/** A working provider, active in Mixed mode, with every option disabled. */
const workingProvider = {
  authentication_conditions: {
    ...disabledMapping,
    authorized_values: [],
    blacklist_client_addresses: [],
    trusted_client_addresses: []
  },
  authentication_type: 'client_secret_post',
  authorization_endpoint: oidcConfig.authorizationEndpoint,
  auto_import: false,
  base_url: oidcConfig.baseUrl,
  client_id: oidcConfig.clientId,
  client_secret: oidcConfig.clientSecret,
  connection_scopes: [oidcConfig.scopes],
  contact_template: null,
  email_bind_attribute: null,
  endsession_endpoint: '/logout',
  fullname_bind_attribute: null,
  groups_mapping: { ...disabledMapping, relations: [] },
  introspection_token_endpoint: oidcConfig.introspectionTokenEndpoint,
  is_active: true,
  is_forced: false,
  login_claim: oidcConfig.loginAttributePath,
  redirect_url: null,
  roles_mapping: {
    ...disabledMapping,
    apply_only_first_role: false,
    relations: []
  },
  token_endpoint: oidcConfig.tokenEndpoint,
  userinfo_endpoint: '/userinfo',
  verify_peer: false
};

type ProviderSettings = Record<string, unknown>;

interface ReadRelation {
  access_group?: { id: number };
  claim_value?: string;
  contact_group?: { id: number };
  group_value?: string;
  priority?: number;
}

/** The GET body names the related objects, the PUT body takes their ids. */
const writable = (settings: ProviderSettings): ProviderSettings => {
  const mapping = (key: string, toWrite: (r: ReadRelation) => unknown) => {
    const value = settings[key] as { relations: Array<ReadRelation> };
    return { ...value, relations: value.relations.map(toWrite) };
  };

  return {
    ...settings,
    groups_mapping: mapping('groups_mapping', (r) => ({
      contact_group_id: r.contact_group?.id,
      group_value: r.group_value
    })),
    roles_mapping: mapping('roles_mapping', (r) => ({
      access_group_id: r.access_group?.id,
      claim_value: r.claim_value,
      priority: r.priority
    }))
  };
};

/**
 * Configure the OpenID provider through the API: a working provider plus the
 * options a test needs. `restore` puts back the settings found beforehand.
 */
export const oidcProvider = async (
  adminApi: CentreonApi,
  options: ProviderSettings = {}
): Promise<{ restore: () => Promise<void> }> => {
  const original = await adminApi.v2<ProviderSettings>('GET', providerPath);
  await adminApi.v2('PUT', providerPath, { ...workingProvider, ...options });

  return {
    restore: async () => {
      await adminApi.v2('PUT', providerPath, writable(original));
    }
  };
};
