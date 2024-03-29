import {
  GroupsRelation,
  SharedAuthenticationConditions,
  SharedGroupsMapping,
  SharedRolesMapping
} from '../shared/models';
import {
  SharedAuthenticationConditionsToAPI,
  SharedGroupsMappingToAPI,
  SharedRolesMappingToAPI
} from '../shared/modelsAPI';

export enum EndpointType {
  CustomEndpoint = 'custom_endpoint',
  IntrospectionEndpoint = 'introspection_endpoint',
  UserInformationEndpoint = 'user_information_endpoint'
}

export interface NamedEntity {
  id: number;
  name: string;
}

export interface EndpointToAPI {
  custom_endpoint?: string | null;
  type: EndpointType;
}

export interface Endpoint {
  customEndpoint?: string | null;
  type: EndpointType;
}

export interface RolesMapping extends SharedRolesMapping {
  endpoint: Endpoint;
}

export interface AuthConditions extends SharedAuthenticationConditions {
  blacklistClientAddresses: Array<string>;
  endpoint: Endpoint;
  trustedClientAddresses: Array<string>;
}

export interface RolesMappingToApi extends SharedRolesMappingToAPI {
  endpoint: EndpointToAPI;
}

export interface GroupsMapping extends SharedGroupsMapping {
  endpoint: Endpoint;
  relations: Array<GroupsRelation>;
}

export interface GroupsMappingToAPI extends SharedGroupsMappingToAPI {
  endpoint: EndpointToAPI;
}

export interface AuthConditionsToApi
  extends SharedAuthenticationConditionsToAPI {
  blacklist_client_addresses: Array<string>;
  endpoint: EndpointToAPI;
  trusted_client_addresses: Array<string>;
}

export interface OpenidConfiguration {
  authenticationConditions: AuthConditions;
  authenticationType: string | null;
  authorizationEndpoint: string | null;
  autoImport: boolean;
  baseUrl: string | null;
  clientId: string | null;
  clientSecret: string | null;
  connectionScopes: Array<string>;
  contactTemplate: NamedEntity | null;
  emailBindAttribute?: string | null;
  endSessionEndpoint?: string | null;
  fullnameBindAttribute?: string | null;
  groupsMapping: GroupsMapping;
  introspectionTokenEndpoint?: string | null;
  isActive: boolean;
  isForced: boolean;
  loginClaim?: string | null;
  redirectUrl?: string | null;
  rolesMapping: RolesMapping;
  tokenEndpoint: string | null;
  userinfoEndpoint?: string | null;
  verifyPeer: boolean;
}

export interface OpenidConfigurationToAPI {
  authentication_conditions: AuthConditionsToApi;
  authentication_type: string | null;
  authorization_endpoint: string | null;
  auto_import: boolean;
  base_url: string | null;
  client_id: string | null;
  client_secret: string | null;
  connection_scopes: Array<string>;
  contact_template: NamedEntity | null;
  email_bind_attribute: string | null;
  endsession_endpoint?: string | null;
  fullname_bind_attribute: string | null;
  groups_mapping: GroupsMappingToAPI;
  introspection_token_endpoint?: string | null;
  is_active: boolean;
  is_forced: boolean;
  login_claim?: string | null;
  redirect_url?: string | null;
  roles_mapping: RolesMappingToApi;
  token_endpoint: string | null;
  userinfo_endpoint?: string | null;
  verify_peer: boolean;
}

export enum AuthenticationType {
  ClientSecretBasic = 'client_secret_basic',
  ClientSecretPost = 'client_secret_post'
}
