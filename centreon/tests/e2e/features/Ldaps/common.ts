// LDAP configuration of the CI stack (openldap profile, glauth server)
const ldapConfigurationName = 'openldap';

// Groups of the glauth server are groupOfUniqueNames entries named by "ou"
// and listing their members in uniqueMember: the group search of the stack
// configuration (groupOfNames, member, cn) finds none of them. The trailing
// posixGroup alternative makes glauth search its groups branch.
const glauthGroupSearch = {
  group_filter:
    '(&(ou=%s)(|(objectClass=groupOfUniqueNames)(objectClass=posixGroup)))',
  group_member: 'uniqueMember',
  group_name: 'ou'
};

const buildContactQuery = (): string =>
  `SELECT c.contact_id, c.contact_auth_type, c.ar_id, t.contact_name AS template_name
  FROM contact c
  LEFT JOIN contact t ON t.contact_id = c.contact_template_id
  WHERE c.contact_alias = ?`;

const buildContactGroupsQuery = (): string =>
  `SELECT cg.cg_name
  FROM contactgroup_contact_relation r
  INNER JOIN contactgroup cg ON cg.cg_id = r.contactgroup_cg_id
  INNER JOIN contact c ON c.contact_id = r.contact_contact_id
  WHERE c.contact_alias = ?`;

const buildLdapConfigurationIdQuery = (): string =>
  'SELECT ar_id FROM auth_ressource WHERE ar_name = ?';

export {
  buildContactGroupsQuery,
  buildContactQuery,
  buildLdapConfigurationIdQuery,
  glauthGroupSearch,
  ldapConfigurationName
};
