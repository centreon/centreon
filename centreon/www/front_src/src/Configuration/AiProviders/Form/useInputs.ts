import { Group, InputProps, InputType } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { modalStateAtom } from '../../ConfigurationBase/atoms';
import { AiProviderType } from '../models';
import {
  labelApiToken,
  labelApiTokenHint,
  labelConnection,
  labelDefaultModel,
  labelDescription,
  labelEndpointUrl,
  labelGeneralInformation,
  labelIsDefault,
  labelName,
  labelType
} from '../translatedLabels';
import { aiProviderTypeLabels, defaultEndpointUrls } from '../utils';

export const useInputs = (): {
  groups: Array<Group>;
  inputs: Array<InputProps>;
} => {
  const { t } = useTranslation();

  const { mode } = useAtomValue(modalStateAtom);
  const isEditing = equals(mode, 'edit');

  return {
    groups: [
      {
        isDividerHidden: true,
        name: t(labelGeneralInformation),
        order: 1
      },
      {
        isDividerHidden: true,
        name: t(labelConnection),
        order: 2
      }
    ],
    inputs: [
      {
        fieldName: 'name',
        group: t(labelGeneralInformation),
        label: t(labelName),
        required: true,
        type: InputType.Text
      },
      {
        fieldName: 'description',
        group: t(labelGeneralInformation),
        label: t(labelDescription),
        text: {
          multilineRows: 2
        },
        type: InputType.Text
      },
      {
        change: ({ setFieldValue, value }): void => {
          // the change callback replaces the default behavior: set the field first
          setFieldValue('type', value);

          const defaultEndpointUrl =
            defaultEndpointUrls[value as AiProviderType];
          if (defaultEndpointUrl) {
            setFieldValue('endpointUrl', defaultEndpointUrl);
          }
        },
        fieldName: 'type',
        getDisabled: () => isEditing,
        group: t(labelGeneralInformation),
        label: t(labelType),
        radio: {
          options: Object.values(AiProviderType).map((type) => ({
            label: t(aiProviderTypeLabels[type]),
            value: type
          })),
          row: true
        },
        required: true,
        type: InputType.Radio
      },
      {
        fieldName: 'isDefault',
        group: t(labelGeneralInformation),
        label: t(labelIsDefault),
        type: InputType.Switch
      },
      {
        fieldName: 'endpointUrl',
        group: t(labelConnection),
        label: t(labelEndpointUrl),
        required: true,
        type: InputType.Text
      },
      {
        fieldName: 'apiToken',
        group: t(labelConnection),
        label: t(labelApiToken),
        required: !isEditing,
        text: {
          placeholder: isEditing ? t(labelApiTokenHint) : undefined
        },
        type: InputType.Password
      },
      {
        fieldName: 'defaultModel',
        group: t(labelConnection),
        label: t(labelDefaultModel),
        type: InputType.Text
      }
    ]
  };
};
