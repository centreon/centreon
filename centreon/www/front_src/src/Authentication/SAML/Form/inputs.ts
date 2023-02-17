import { not, prop } from 'ramda';
import { FormikValues } from 'formik';

import { InputType } from '@centreon/ui';
import type { InputProps } from '@centreon/ui';

import {
  labelBothIdentityProviderAndCentreonUI,
  labelCentreonUIOnly,
  labelCertificate,
  labelDefineRelationBetweenRolesAndAclAccessGroups,
  labelEmailAttribute,
  labelEnableSAMLAuthentication,
  labelFullNameAttribute,
  labelEntityIdURL,
  labelLogoutFrom,
  labelLogoutUrl,
  labelRemoteLoginUrl,
  labelSAMLOnly,
  labelUserIdAttribute
} from '../translatedLabels';
import {
  labelActivation,
  labelAuthenticationConditions,
  labelAutoImportUsers,
  labelGroupsMapping,
  labelIdentityProvider
} from '../../translatedLabels';
import {
  labelAclAccessGroup,
  labelApplyOnlyFirtsRole,
  labelAuthenticationMode,
  labelConditionValue,
  labelConditionsAttributePath,
  labelContactGroup,
  labelContactTemplate,
  labelDefineAuthorizedConditionsValues,
  labelDefinedTheRelationBetweenGroupsAndContactGroups,
  labelDeleteRelation,
  labelEnableAutoImport,
  labelEnableAutomaticManagement,
  labelEnableConditionsOnIdentityProvider,
  labelGroupValue,
  labelGroupsAttributePath,
  labelMixed,
  labelRoleValue,
  labelRolesAttributePath,
  labelRolesMapping
} from '../../shared/translatedLabels';
import {
  accessGroupsEndpoint,
  contactGroupsEndpoint,
  contactTemplatesEndpoint
} from '../../api/endpoints';

const isAutoImportDisabled = (values: FormikValues): boolean =>
  not(prop('autoImport', values));

const isAutoImportEnabled = (values: FormikValues): boolean =>
  prop('autoImport', values);

const authenticationConditions: Array<InputProps> = [
  {
    dataTestId: 'saml_authenticationConditions.isEnabled',
    fieldName: 'authenticationConditions.isEnabled',
    group: labelAuthenticationConditions,
    label: labelEnableConditionsOnIdentityProvider,
    type: InputType.Switch
  },
  {
    dataTestId: 'saml_authenticationConditions.attributePath',
    fieldName: 'authenticationConditions.attributePath',
    group: labelAuthenticationConditions,
    label: labelConditionsAttributePath,
    type: InputType.Text
  },
  {
    fieldName: 'authenticationConditions.authorizedValues',
    fieldsTable: {
      columns: [
        {
          dataTestId: 'oidc_authenticationConditions.authorizedValues',
          fieldName: '',
          label: labelConditionValue,
          type: InputType.Text
        }
      ],
      defaultRowValue: {
        conditionValue: ''
      },
      deleteLabel: labelDeleteRelation,
      hasSingleValue: true
    },
    group: labelAuthenticationConditions,
    label: labelDefineAuthorizedConditionsValues,
    type: InputType.FieldsTable
  }
];

const rolesMapping: Array<InputProps> = [
  {
    dataTestId: 'saml_rolesMapping.isEnabled',
    fieldName: 'rolesMapping.isEnabled',
    group: labelRolesMapping,
    label: labelEnableAutomaticManagement,
    type: InputType.Switch
  },
  {
    dataTestId: 'saml_rolesMapping.applyOnlyFirstRole',
    fieldName: 'rolesMapping.applyOnlyFirstRole',
    group: labelRolesMapping,
    label: labelApplyOnlyFirtsRole,
    type: InputType.Switch
  },
  {
    dataTestId: 'saml_rolesMapping.attributePath',
    fieldName: 'rolesMapping.attributePath',
    group: labelRolesMapping,
    label: labelRolesAttributePath,
    type: InputType.Text
  },
  {
    fieldName: 'rolesMapping.relations',
    fieldsTable: {
      columns: [
        {
          dataTestId: 'saml_claimValue',
          fieldName: 'claimValue',
          label: labelRoleValue,
          type: InputType.Text
        },
        {
          connectedAutocomplete: {
            additionalConditionParameters: [],
            endpoint: accessGroupsEndpoint
          },
          dataTestId: 'saml_accessGroup',
          fieldName: 'accessGroup',
          label: labelAclAccessGroup,
          type: InputType.SingleConnectedAutocomplete
        }
      ],
      defaultRowValue: {
        accessGroup: null,
        claimValue: ''
      },
      deleteLabel: labelDeleteRelation,
      getSortable: (values: FormikValues): boolean =>
        prop('applyOnlyFirstRole', values?.rolesMapping)
    },
    group: labelRolesMapping,
    label: labelDefineRelationBetweenRolesAndAclAccessGroups,
    type: InputType.FieldsTable
  }
];

const groupsMapping: Array<InputProps> = [
  {
    dataTestId: 'saml_groupsMapping.isEnabled',
    fieldName: 'groupsMapping.isEnabled',
    group: labelGroupsMapping,
    label: labelEnableAutomaticManagement,
    type: InputType.Switch
  },
  {
    dataTestId: 'saml_groupsMapping.attributePath',
    fieldName: 'groupsMapping.attributePath',
    group: labelGroupsMapping,
    label: labelGroupsAttributePath,
    type: InputType.Text
  },
  {
    fieldName: 'groupsMapping.relations',
    fieldsTable: {
      columns: [
        {
          dataTestId: 'saml_groupValue',
          fieldName: 'groupValue',
          label: labelGroupValue,
          type: InputType.Text
        },
        {
          connectedAutocomplete: {
            additionalConditionParameters: [],
            endpoint: contactGroupsEndpoint
          },
          dataTestId: 'saml_contactGroup',
          fieldName: 'contactGroup',
          label: labelContactGroup,
          type: InputType.SingleConnectedAutocomplete
        }
      ],
      defaultRowValue: {
        contactGroup: null,
        groupValue: ''
      },
      deleteLabel: labelDeleteRelation
    },
    group: labelGroupsMapping,
    label: labelDefinedTheRelationBetweenGroupsAndContactGroups,
    type: InputType.FieldsTable
  }
];

export const inputs: Array<InputProps> = [
  {
    dataTestId: 'saml_enableAuthentication',
    fieldName: 'isActive',
    group: labelActivation,
    label: labelEnableSAMLAuthentication,
    type: InputType.Switch
  },
  {
    dataTestId: 'saml_activationMode',
    fieldName: 'isForced',
    group: labelActivation,
    label: labelAuthenticationMode,
    radio: {
      options: [
        {
          label: labelSAMLOnly,
          value: true
        },
        {
          label: labelMixed,
          value: false
        }
      ]
    },
    type: InputType.Radio
  },
  {
    dataTestId: 'saml_remoteLoginUrl',
    fieldName: 'remoteLoginUrl',
    group: labelIdentityProvider,
    label: labelRemoteLoginUrl,
    required: true,
    type: InputType.Text
  },
  {
    dataTestId: 'oidc_endityIdUrl',
    fieldName: 'entityIdUrl',
    group: labelIdentityProvider,
    label: labelEntityIdURL,
    required: true,
    type: InputType.Text
  },
  {
    dataTestId: 'saml_certificate',
    fieldName: 'certificate',
    group: labelIdentityProvider,
    label: labelCertificate,
    required: true,
    text: {
      multilineRows: 4
    },
    type: InputType.Text
  },
  {
    dataTestId: 'saml_userIdAttribute',
    fieldName: 'userIdAttribute',
    group: labelIdentityProvider,
    label: labelUserIdAttribute,
    required: true,
    type: InputType.Text
  },
  {
    dataTestId: 'saml_logoutFrom',
    fieldName: 'logoutFrom',
    group: labelIdentityProvider,
    label: labelLogoutFrom,
    radio: {
      options: [
        {
          label: labelCentreonUIOnly,
          value: false
        },
        {
          label: labelBothIdentityProviderAndCentreonUI,
          value: true
        }
      ]
    },
    type: InputType.Radio
  },
  {
    dataTestId: 'saml_logoutFromUrl',
    fieldName: 'logoutFromUrl',
    getRequired: (values: FormikValues): boolean => values.logoutFrom,
    group: labelIdentityProvider,
    hideInput: (values: FormikValues): boolean => !values.logoutFrom,
    label: labelLogoutUrl,
    type: InputType.Text
  },
  {
    dataTestId: 'saml_autoImport',
    fieldName: 'autoImport',
    group: labelAutoImportUsers,
    label: labelEnableAutoImport,
    type: InputType.Switch
  },
  {
    connectedAutocomplete: {
      additionalConditionParameters: [],
      endpoint: contactTemplatesEndpoint
    },
    dataTestId: 'saml_contactTemplate',
    fieldName: 'contactTemplate',
    getDisabled: isAutoImportDisabled,
    getRequired: isAutoImportEnabled,
    group: labelAutoImportUsers,
    label: labelContactTemplate,
    type: InputType.SingleConnectedAutocomplete
  },
  {
    dataTestId: 'saml_emailBindAttribute',
    fieldName: 'emailBindAttribute',
    getDisabled: isAutoImportDisabled,
    getRequired: isAutoImportEnabled,
    group: labelAutoImportUsers,
    label: labelEmailAttribute,
    type: InputType.Text
  },
  {
    dataTestId: 'saml_fullnameBindAttribute',
    fieldName: 'fullnameBindAttribute',
    getDisabled: isAutoImportDisabled,
    getRequired: isAutoImportEnabled,
    group: labelAutoImportUsers,
    label: labelFullNameAttribute,
    type: InputType.Text
  },
  ...groupsMapping,
  ...rolesMapping,
  ...authenticationConditions
];
