export const retrievedOpenidConfiguration = {
  authentication_conditions: {
    attribute_path: 'auth attribute path',
    authorized_values: ['authorized'],
    blacklist_client_addresses: ['127.0.0.1'],
    endpoint: {
      custom_endpoint: null,
      type: 'introspection_endpoint'
    },
    is_enabled: false,
    trusted_client_addresses: ['127.0.0.1']
  },
  authentication_type: 'client_secret_post',
  authorization_endpoint: '/authorize',
  auto_import: false,
  base_url: 'https://localhost:8080',
  client_id: 'client_id',
  client_secret: 'client_secret',
  connection_scopes: ['openid'],
  contact_template: null,
  email_bind_attribute: 'email',
  endsession_endpoint: '/logout',
  fullname_bind_attribute: 'lastname',
  groups_mapping: {
    attribute_path: 'group attribute path',
    endpoint: {
      custom_endpoint: '/group/endpoint',
      type: 'custom_endpoint'
    },
    is_enabled: true,
    relations: []
  },
  introspection_token_endpoint: '/introspect',
  is_active: true,
  is_forced: false,
  login_claim: 'sub',
  redirect_url: '',
  roles_mapping: {
    apply_only_first_role: true,
    attribute_path: 'role attribute path',
    endpoint: {
      custom_endpoint: '/role/endpoint',
      type: 'custom_endpoint'
    },
    is_enabled: false,
    relations: []
  },
  token_endpoint: '/token',
  userinfo_endpoint: '/userinfo',
  verify_peer: false
};
