const externalLegacyEndpoint = './api/external.php';
const internalLegacyEndpoint = './api/internal.php';
const externalTranslationEndpoint = `${externalLegacyEndpoint}?object=centreon_i18n&action=allTranslations`;
const internalTranslationEndpoint = `${internalLegacyEndpoint}?object=centreon_i18n&action=allTranslations`;
const baseEndpoint = './api/latest';
const userEndpoint = `${baseEndpoint}/configuration/users/current/parameters`;
const parametersEndpoint = `${baseEndpoint}/administration/parameters`;
const userPermissionsEndpoint = `${baseEndpoint}/users/acl/permissions`;
const aclEndpoint = `${baseEndpoint}/users/acl/actions`;

export {
  parametersEndpoint,
  externalTranslationEndpoint,
  internalTranslationEndpoint,
  aclEndpoint,
  userEndpoint,
  userPermissionsEndpoint
};
