import { Group, InputProps, InputType } from '@centreon/ui';
import { useTranslation } from 'react-i18next';

import { platformFeaturesAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import IconFiled from './IconFilled';

import {
  hostListEndpoint,
  resourceAccessRulesEndpoint
} from '../api/endpoints';
import {
  labelAlias,
  labelApplyResourceAccessRule,
  labelComments,
  labelExtendedInformation,
  labelGeneralInformation,
  labelGeographicCoordinates,
  labelGroupMembers,
  labelName,
  labelResourceAccessRule,
  labelSelectHosts
} from '../translatedLabels';
import { useFormStyles } from './Form.styles';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

const useFormInputs = ({
  hasWriteAccess
}: { hasWriteAccess: boolean }): FormInputsState => {
  const { t } = useTranslation();
  const { classes } = useFormStyles();

  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const titleAttributes = {
    classes: { root: classes.titleGroup },
    variant: 'subtitle1'
  };

  const groups = [
    {
      name: t(labelGeneralInformation),
      order: 1,
      titleAttributes,
      isDividerHidden: true
    },
    {
      name: t(labelGroupMembers),
      order: 2,
      titleAttributes,
      isDividerHidden: true
    },
    ...(isCloudPlatform
      ? [
          {
            name: t(labelResourceAccessRule),
            order: 3,
            titleAttributes,
            isDividerHidden: true
          }
        ]
      : []),
    { name: t(labelExtendedInformation), order: 4, titleAttributes }
  ];

  const inputs = [
    {
      type: InputType.Grid,
      group: t(labelGeneralInformation),
      grid: {
        columns: [
          {
            dataTestId: labelName,
            fieldName: 'name',
            group: t(labelGeneralInformation),
            label: t(labelName),
            required: hasWriteAccess,
            type: InputType.Text,
            getDisabled: () => !hasWriteAccess
          },
          {
            fieldName: 'alias',
            group: t(labelGeneralInformation),
            label: t(labelAlias),
            type: InputType.Text,
            getDisabled: () => !hasWriteAccess
          }
        ]
      }
    },
    {
      connectedAutocomplete: {
        chipColor: 'primary',
        additionalConditionParameters: [],
        endpoint: hostListEndpoint,
        filterKey: 'name'
      },
      fieldName: 'hosts',
      group: t(labelGroupMembers),
      label: t(labelSelectHosts),
      getDisabled: () => !hasWriteAccess,
      type: InputType.MultiConnectedAutocomplete
    },
    {
      connectedAutocomplete: {
        chipColor: 'primary',
        additionalConditionParameters: [],
        endpoint: resourceAccessRulesEndpoint,
        filterKey: 'name'
      },
      fieldName: 'resourceAccessRules',
      group: t(labelResourceAccessRule),
      label: t(labelApplyResourceAccessRule),
      getDisabled: () => !hasWriteAccess,
      type: InputType.MultiConnectedAutocomplete
    },
    {
      type: InputType.Grid,
      group: t(labelExtendedInformation),
      grid: {
        columns: [
          {
            fieldName: 'geoCoords',
            label: t(labelGeographicCoordinates),
            getDisabled: () => !hasWriteAccess,
            type: InputType.Text
          },
          {
            custom: { Component: IconFiled },
            type: InputType.Custom,
            disabled: !hasWriteAccess
          }
        ]
      }
    },
    {
      fieldName: 'comment',
      group: t(labelExtendedInformation),
      label: t(labelComments),
      getDisabled: () => !hasWriteAccess,
      text: {
        multilineRows: 3
      },
      type: InputType.Text
    }
  ];

  return { inputs, groups };
};

export default useFormInputs;
