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
  labelDefaultModelPlaceholder,
  labelDescription,
  labelEnabled,
  labelEndpointUrl,
  labelFastModel,
  labelFastModelPlaceholder,
  labelGeneralInformation,
  labelName,
  labelType
} from '../translatedLabels';
import { aiProviderTypeLabels, defaultModels } from '../utils';

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
        change: ({ setValues, value }): void => {
          // the change callback replaces the default behavior, and the
          // updater form is needed: the values passed to the callback can
          // be stale since the radio does not re-render on other fields
          setValues((currentValues) => {
            // default the name and models for the provider type while the
            // user has not chosen them (empty, or still an auto-filled
            // value of a previously selected type)
            const typeLabels = Object.values(aiProviderTypeLabels).map(
              (label) => t(label)
            );
            const autoFilledModels = Object.values(defaultModels)
              .flatMap((models) => [models.defaultModel, models.fastModel])
              .filter(Boolean);

            const keepName =
              currentValues.name && !typeLabels.includes(currentValues.name);
            const keepDefaultModel =
              currentValues.defaultModel &&
              !autoFilledModels.includes(currentValues.defaultModel);
            const keepFastModel =
              currentValues.fastModel &&
              !autoFilledModels.includes(currentValues.fastModel);

            const models = defaultModels[value as AiProviderType];

            return {
              ...currentValues,
              defaultModel: keepDefaultModel
                ? currentValues.defaultModel
                : (models.defaultModel ?? ''),
              fastModel: keepFastModel
                ? currentValues.fastModel
                : models.fastModel,
              name: keepName
                ? currentValues.name
                : t(aiProviderTypeLabels[value as AiProviderType]),
              type: value
            };
          });
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
        fieldName: 'isActivated',
        group: t(labelGeneralInformation),
        // at most one provider is enabled: enabling this one disables the other
        label: t(labelEnabled),
        type: InputType.Switch
      },
      {
        fieldName: 'endpointUrl',
        group: t(labelConnection),
        // the endpoint of curated providers is enforced by the API
        hideInput: (values) =>
          !equals(values.type, AiProviderType.OpenAiCompatible),
        label: t(labelEndpointUrl),
        required: true,
        type: InputType.Text
      },
      {
        fieldName: 'apiToken',
        group: t(labelConnection),
        label: t(labelApiToken),
        // also required while editing: leaving it empty keeps the current
        // token (see the placeholder), and the Form component would move
        // a non-required field below the required ones
        required: true,
        text: {
          placeholder: isEditing ? t(labelApiTokenHint) : undefined
        },
        type: InputType.Password
      },
      {
        fieldName: 'defaultModel',
        group: t(labelConnection),
        label: t(labelDefaultModel),
        required: true,
        text: {
          placeholder: t(labelDefaultModelPlaceholder)
        },
        type: InputType.Text
      },
      {
        fieldName: 'fastModel',
        group: t(labelConnection),
        label: t(labelFastModel),
        text: {
          placeholder: t(labelFastModelPlaceholder)
        },
        type: InputType.Text
      }
    ]
  };
};
