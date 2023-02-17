import {
  NamedEntity,
  SharedAuthenticationConditions,
  SharedGroupsMapping,
  SharedRolesMapping
} from '../shared/models';
import {
  SharedRolesMappingToAPI,
  SharedGroupsMappingToAPI,
  SharedAuthenticationConditionsToAPI
} from '../shared/modelsAPI';

export interface SAMLConfiguration {
  authenticationConditions: SharedAuthenticationConditions;
  autoImport: boolean;
  certificate: string;
  contactTemplate: NamedEntity | null;
  emailBindAttribute?: string | null;
  entityIdUrl: string;
  fullnameBindAttribute?: string | null;
  groupsMapping: SharedGroupsMapping;
  isActive: boolean;
  isForced: boolean;
  logoutFrom: boolean;
  logoutFromUrl?: string | null;
  remoteLoginUrl: string;
  rolesMapping: SharedRolesMapping;
  userIdAttribute: string;
}

export interface SAMLConfigurationToAPI {
  authentication_conditions: SharedAuthenticationConditionsToAPI;
  auto_import: boolean;
  certificate: string;
  contact_template: NamedEntity | null;
  email_bind_attribute?: string | null;
  entity_id_url: string;
  fullname_bind_attribute?: string | null;
  groups_mapping: SharedGroupsMappingToAPI;
  is_active: boolean;
  is_forced: boolean;
  logout_from: boolean;
  logout_from_url: string | null;
  remote_login_url: string;
  roles_mapping: SharedRolesMappingToAPI;
  user_id_attribute: string;
}
