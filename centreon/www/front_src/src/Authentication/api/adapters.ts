import {
  PasswordSecurityPolicy,
  PasswordSecurityPolicyToAPI
} from '../Local/models';
import {
  OpenidConfiguration,
  OpenidConfigurationToAPI,
  AuthConditions,
  AuthConditionsToApi,
  Endpoint,
  EndpointToAPI,
  GroupsMapping,
  GroupsMappingToAPI,
  RolesMapping,
  RolesMappingToApi
} from '../Openid/models';
import { SAMLConfiguration, SAMLConfigurationToAPI } from '../SAML/models';
import {
  WebSSOConfiguration,
  WebSSOConfigurationToAPI
} from '../WebSSO/models';
import {
  adaptGroupsRelationsToAPI,
  adaptRolesRelationsToAPI
} from '../shared/adapters';
import {
  SharedAuthenticationConditions,
  SharedGroupsMapping,
  SharedRolesMapping
} from '../shared/models';
import {
  SharedAuthenticationConditionsToAPI,
  SharedGroupsMappingToAPI,
  SharedRolesMappingToAPI
} from '../shared/modelsAPI';

export const adaptPasswordSecurityPolicyFromAPI = (
  securityPolicy: PasswordSecurityPolicy
): PasswordSecurityPolicy => {
  return {
    ...securityPolicy,
    blockingDuration: securityPolicy.blockingDuration
      ? securityPolicy.blockingDuration * 1000
      : null,
    delayBeforeNewPassword: securityPolicy.delayBeforeNewPassword
      ? securityPolicy.delayBeforeNewPassword * 1000
      : null,
    passwordExpiration: {
      ...securityPolicy.passwordExpiration,
      expirationDelay: securityPolicy.passwordExpiration.expirationDelay
        ? securityPolicy.passwordExpiration.expirationDelay * 1000
        : null
    }
  };
};

export const adaptPasswordSecurityPolicyToAPI = ({
  passwordMinLength,
  delayBeforeNewPassword,
  canReusePasswords,
  passwordExpiration,
  hasSpecialCharacter,
  hasNumber,
  hasLowerCase,
  hasUpperCase,
  attempts,
  blockingDuration
}: PasswordSecurityPolicy): PasswordSecurityPolicyToAPI => {
  return {
    password_security_policy: {
      attempts,
      blocking_duration: blockingDuration ? blockingDuration / 1000 : null,
      can_reuse_passwords: canReusePasswords,
      delay_before_new_password: delayBeforeNewPassword
        ? delayBeforeNewPassword / 1000
        : null,
      has_lowercase: hasLowerCase,
      has_number: hasNumber,
      has_special_character: hasSpecialCharacter,
      has_uppercase: hasUpperCase,
      password_expiration: {
        excluded_users: passwordExpiration.excludedUsers,
        expiration_delay: passwordExpiration.expirationDelay
          ? passwordExpiration.expirationDelay / 1000
          : null
      },
      password_min_length: passwordMinLength
    }
  };
};

const adaptEndpoint = ({ customEndpoint, type }: Endpoint): EndpointToAPI => {
  return {
    custom_endpoint: customEndpoint,
    type
  };
};

const adaptAuthentificationConditions = ({
  trustedClientAddresses,
  blacklistClientAddresses,
  attributePath,
  endpoint,
  isEnabled,
  authorizedValues
}: AuthConditions): AuthConditionsToApi => {
  return {
    attribute_path: attributePath,
    authorized_values: authorizedValues,
    blacklist_client_addresses: blacklistClientAddresses,
    endpoint: adaptEndpoint(endpoint),
    is_enabled: isEnabled,
    trusted_client_addresses: trustedClientAddresses
  };
};

const adaptRolesMapping = ({
  applyOnlyFirstRole,
  attributePath,
  endpoint,
  isEnabled,
  relations
}: RolesMapping): RolesMappingToApi => {
  return {
    apply_only_first_role: applyOnlyFirstRole,
    attribute_path: attributePath,
    endpoint: adaptEndpoint(endpoint),
    is_enabled: isEnabled,
    relations: adaptRolesRelationsToAPI(relations)
  };
};

const adaptGroupsMapping = ({
  attributePath,
  endpoint,
  isEnabled,
  relations
}: GroupsMapping): GroupsMappingToAPI => {
  return {
    attribute_path: attributePath,
    endpoint: adaptEndpoint(endpoint),
    is_enabled: isEnabled,
    relations: adaptGroupsRelationsToAPI(relations)
  };
};

export const adaptOpenidConfigurationToAPI = ({
  authenticationType,
  authorizationEndpoint,
  baseUrl,
  clientId,
  clientSecret,
  connectionScopes,
  endSessionEndpoint,
  introspectionTokenEndpoint,
  isActive,
  isForced,
  loginClaim,
  tokenEndpoint,
  userinfoEndpoint,
  verifyPeer,
  autoImport,
  contactTemplate,
  emailBindAttribute,
  fullnameBindAttribute,
  authenticationConditions,
  rolesMapping,
  groupsMapping
}: OpenidConfiguration): OpenidConfigurationToAPI => ({
  authentication_conditions: adaptAuthentificationConditions(
    authenticationConditions
  ),
  authentication_type: authenticationType || null,
  authorization_endpoint: authorizationEndpoint || null,
  auto_import: autoImport,
  base_url: baseUrl || null,
  client_id: clientId || null,
  client_secret: clientSecret || null,
  connection_scopes: connectionScopes,
  contact_template: contactTemplate || null,
  email_bind_attribute: emailBindAttribute || null,
  endsession_endpoint: endSessionEndpoint || null,
  fullname_bind_attribute: fullnameBindAttribute || null,
  groups_mapping: adaptGroupsMapping(groupsMapping),
  introspection_token_endpoint: introspectionTokenEndpoint || null,
  is_active: isActive,
  is_forced: isForced,
  login_claim: loginClaim || null,
  roles_mapping: adaptRolesMapping(rolesMapping),
  token_endpoint: tokenEndpoint || null,
  userinfo_endpoint: userinfoEndpoint || null,
  verify_peer: verifyPeer
});

export const adaptWebSSOConfigurationToAPI = ({
  loginHeaderAttribute,
  patternMatchingLogin,
  patternReplaceLogin,
  blacklistClientAddresses,
  isActive,
  isForced,
  trustedClientAddresses
}: WebSSOConfiguration): WebSSOConfigurationToAPI => ({
  blacklist_client_addresses: blacklistClientAddresses,
  is_active: isActive,
  is_forced: isForced,
  login_header_attribute: loginHeaderAttribute || null,
  pattern_matching_login: patternMatchingLogin || null,
  pattern_replace_login: patternReplaceLogin || null,
  trusted_client_addresses: trustedClientAddresses
});

const adaptSAMLRolesMapping = ({
  applyOnlyFirstRole,
  attributePath,
  isEnabled,
  relations
}: SharedRolesMapping): SharedRolesMappingToAPI => {
  return {
    apply_only_first_role: applyOnlyFirstRole,
    attribute_path: attributePath,
    is_enabled: isEnabled,
    relations: adaptRolesRelationsToAPI(relations)
  };
};

const adaptSAMLGroupsMapping = ({
  attributePath,
  isEnabled,
  relations
}: SharedGroupsMapping): SharedGroupsMappingToAPI => {
  return {
    attribute_path: attributePath,
    is_enabled: isEnabled,
    relations: adaptGroupsRelationsToAPI(relations)
  };
};

const adaptSAMLAuthentificationConditions = ({
  attributePath,
  isEnabled,
  authorizedValues
}: SharedAuthenticationConditions): SharedAuthenticationConditionsToAPI => {
  return {
    attribute_path: attributePath,
    authorized_values: authorizedValues,
    is_enabled: isEnabled
  };
};

export const adaptSAMLConfigurationToAPI = ({
  isActive,
  isForced,
  autoImport,
  contactTemplate,
  emailBindAttribute,
  fullnameBindAttribute,
  rolesMapping,
  groupsMapping,
  authenticationConditions,
  certificate,
  entityIdUrl,
  logoutFrom,
  logoutFromUrl,
  remoteLoginUrl,
  userIdAttribute
}: SAMLConfiguration): SAMLConfigurationToAPI => ({
  authentication_conditions: adaptSAMLAuthentificationConditions(
    authenticationConditions
  ),
  auto_import: autoImport,
  certificate,
  contact_template: contactTemplate || null,
  email_bind_attribute: emailBindAttribute || null,
  entity_id_url: entityIdUrl,
  fullname_bind_attribute: fullnameBindAttribute || null,
  groups_mapping: adaptSAMLGroupsMapping(groupsMapping),
  is_active: isActive,
  is_forced: isForced,
  logout_from: logoutFrom,
  logout_from_url: logoutFromUrl,
  remote_login_url: remoteLoginUrl,
  roles_mapping: adaptSAMLRolesMapping(rolesMapping),
  user_id_attribute: userIdAttribute
});
