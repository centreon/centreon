export const retrievedSAMLConfiguration = {
  authentication_conditions: {
    attribute_path: 'info.items.prop1',
    authorized_values: ['string'],
    is_enabled: true
  },
  auto_import: true,
  certificate: 'string',
  contact_template: {
    id: 1,
    name: 'Default'
  },
  email_bind_attribute: 'email',
  entity_id_url: 'https://idp/saml',
  fullname_bind_attribute: 'name',
  groups_mapping: {
    attribute_path: 'info.items.groups',
    is_enabled: true,
    relations: [
      {
        contact_group: {
          id: 1,
          name: 'cg1'
        },
        group_value: 'group1'
      }
    ]
  },
  is_active: true,
  is_forced: false,
  logout_from: true,
  logout_from_url: 'https://idp/saml',
  remote_login_url: 'https://idp/saml',
  roles_mapping: {
    apply_only_first_role: true,
    attribute_path: 'info.items.role',
    is_enabled: true,
    relations: [
      {
        access_group: {
          id: 1,
          name: 'Default'
        },
        claim_value: 'scope',
        priority: 0
      }
    ]
  },
  user_id_attribute: 'string'
};
