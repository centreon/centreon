// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Group, InputProps, InputType } from '@centreon/ui';
import { platformFeaturesAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import {
  getListImagesSearchEndpoint,
  hostListEndpoint,
  resourceAccessRulesEndpoint
} from '../api/endpoints';
import {
  labelAdditionalInformation,
  labelAlias,
  labelApplyResourceAccessRule,
  labelComments,
  labelGeneralInformation,
  labelGeographicCoordinates,
  labelGroupMembers,
  labelIcon,
  labelName,
  labelResourceAccessRule,
  labelSelectHosts
} from '../translatedLabels';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

const useFormInputs = ({ canEdit }: { canEdit: boolean }): FormInputsState => {
  const { t } = useTranslation();

  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const groups = [
    {
      isDividerHidden: true,
      name: t(labelGeneralInformation),
      order: 1
    },
    {
      isDividerHidden: true,
      name: t(labelGroupMembers),
      order: 2
    },
    ...(isCloudPlatform
      ? [
          {
            isDividerHidden: true,
            name: t(labelResourceAccessRule),
            order: 3
          }
        ]
      : []),
    { name: t(labelAdditionalInformation), order: 4 }
  ];

  const inputs = [
    {
      grid: {
        columns: [
          {
            dataTestId: labelName,
            fieldName: 'name',
            getDisabled: () => !canEdit,
            group: t(labelGeneralInformation),
            label: t(labelName),
            required: canEdit,
            type: InputType.Text
          },
          {
            fieldName: 'alias',
            getDisabled: () => !canEdit,
            group: t(labelGeneralInformation),
            label: t(labelAlias),
            type: InputType.Text
          }
        ]
      },
      group: t(labelGeneralInformation),
      type: InputType.Grid
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        chipColor: 'primary',
        disableSelectAll: false,
        endpoint: hostListEndpoint,
        filterKey: 'name',
        limitTags: 15
      },
      fieldName: 'hosts',
      getDisabled: () => !canEdit,
      group: t(labelGroupMembers),
      label: t(labelSelectHosts),
      type: InputType.MultiConnectedAutocomplete
    },
    {
      connectedAutocomplete: {
        additionalConditionParameters: [],
        chipColor: 'primary',
        disableSelectAll: false,
        endpoint: resourceAccessRulesEndpoint,
        filterKey: 'name',
        limitTags: 15
      },
      fieldName: 'resourceAccessRules',
      getDisabled: () => !canEdit,
      group: t(labelResourceAccessRule),
      label: t(labelApplyResourceAccessRule),
      required: canEdit,
      type: InputType.MultiConnectedAutocomplete
    },
    {
      grid: {
        columns: [
          {
            fieldName: 'geoCoords',
            getDisabled: () => !canEdit,
            label: t(labelGeographicCoordinates),
            type: InputType.Text
          },
          {
            fieldName: 'icon',
            getDisabled: () => !canEdit,
            iconSelector: { getEndpoint: getListImagesSearchEndpoint },
            label: t(labelIcon),
            type: InputType.IconSelector
          }
        ]
      },
      group: t(labelAdditionalInformation),
      type: InputType.Grid
    },
    {
      fieldName: 'comment',
      getDisabled: () => !canEdit,
      group: t(labelAdditionalInformation),
      label: t(labelComments),
      text: {
        multilineRows: 3
      },
      type: InputType.Text
    }
  ];

  return { groups, inputs };
};

export default useFormInputs;
