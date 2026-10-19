import type { Credentials } from './credentials';

/**
 * SAML client `centreon` of the `Centreon_SSO` realm, reached at `sso-proxy`
 * like the OpenID provider (see fixtures/oidc.ts). It sends the email as user
 * id, so the realm user `saml` signs in as the contact `saml@localhost`.
 */
const realm = 'http://sso-proxy:8080/realms/Centreon_SSO';

export const samlForm = {
  // Public signing certificate of the realm.
  certificate: 'MIICpzCCAY8CBgGFydyVcDANBgkqhkiG9w0BAQsFADAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wHhcNMjMwMTE5MTE0NzM0WhcNMzMwMTE5MTE0OTE0WjAXMRUwEwYDVQQDDAxDZW50cmVvbl9TU08wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCCpNndecGJI2xOaNQXDDvwDwo/beQ7Q4HW/ck1BNkE13IgPf5GRpvP2jp/1IZsx92vQ2Ub9g5urNG/jo3nZzsUUIdTICsN9Bq2OIjYU9Uxmc1PpHzklN/SqZWbKXOw8EzqXkQ3YNXHqL9omJJ5JMxe4zg758zlvOUh3I44XhMy6PKgeReJIm+HxYJ8SKeu/XVRI7Uiyav5L2M85ED3kqiI3iPrGfLQzv8zqkTeNfuZIeigqI+M8MqRxR3Qf0UlmWA3ZAzsoxJUU+e0tHnD7MhgyRLfg76FjQ1U7Tv7X/h8uqRthjTbva5v0k0M85z21C85UrHxpS3e/HJFInrkJredAgMBAAEwDQYJKoZIhvcNAQELBQADggEBADQANd/iYhefXpcqXC+co3fEe7IaZ93XelZzJ5S4OAR5dHnhMMlMQnnscW/nH8NAEwWRImJPfOEcKun8rBUphZZJxi2WHHj5ilhGdNtcyZzh0sufyIQav/QMreGmDEj/J/uRfmG15Lj1wJB6mw+O4kuwJj/8DzxK6/sQYPisJuXrSWrDmcpvShvbo59JbVjdYK49WXVDbl++7hrwiOYuCQ/uodQYgvChZnIQbL4O6TbG4OLy+prFd5FBsEQds8ZNXoLWM5bCUz+bz4N68fAqhtPR8+yR+pIrE7/cvRaRCmgnG0s61JBZVxHoT4dbMJUTTSSS4dWCUUNhMCIFtEKL06c=',
  entityId: realm,
  remoteLoginUrl: `${realm}/protocol/saml/clients/centreon`,
  userIdAttribute: 'urn:oid:1.2.840.113549.1.9.1'
};

export const samlUser: Credentials = {
  login: 'saml',
  password: 'Centreon!2021'
};

export const samlUserClaims = {
  alias: 'saml@localhost',
  firstName: 'SAML',
  firstNameAttribute: 'urn:oid:2.5.4.42',
  group: '/Supervisors',
  roles: ['centreon-editor', 'default-roles-centreon_sso']
};

/** A working provider, active in Mixed mode, with every option disabled. */
export const workingSamlProvider = {
  authentication_conditions: {
    attribute_path: '',
    authorized_values: [],
    is_enabled: false
  },
  auto_import: false,
  certificate: samlForm.certificate,
  contact_template: null,
  email_bind_attribute: null,
  entity_id_url: samlForm.entityId,
  fullname_bind_attribute: null,
  groups_mapping: { attribute_path: '', is_enabled: false, relations: [] },
  is_active: true,
  is_forced: false,
  logout_from: false,
  logout_from_url: null,
  remote_login_url: samlForm.remoteLoginUrl,
  requested_authn_context: false,
  requested_authn_context_comparison: 'exact',
  roles_mapping: {
    apply_only_first_role: false,
    attribute_path: '',
    is_enabled: false,
    relations: []
  },
  user_id_attribute: samlForm.userIdAttribute
};
