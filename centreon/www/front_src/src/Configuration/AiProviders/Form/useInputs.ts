import { Group, InputProps, InputType } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { modalStateAtom } from '../../ConfigurationBase/atoms';
import { AiProviderType } from '../models';
import {
  labelApiToken,
  labelApiTokenHint,
  labelAwsRegion,
  labelConnection,
  labelCurrency,
  labelDailyLimit,
  labelDefaultModel,
  labelDefaultModelInputPrice,
  labelDefaultModelOutputPrice,
  labelDescription,
  labelEnabled,
  labelEndpointUrl,
  labelFastModel,
  labelFastModelInputPrice,
  labelFastModelOutputPrice,
  labelGeneralInformation,
  labelModels,
  labelMonthlyLimit,
  labelName,
  labelPricing,
  labelSpendLimits,
  labelType
} from '../translatedLabels';
import { aiProviderTypeLabels, defaultModels } from '../utils';
import CurrencyChangeWarning from './CurrencyChangeWarning';
import CurrencyField from './CurrencyField';
import FetchModelsButton from './FetchModelsButton';
import FetchPricesButton from './FetchPricesButton';
import ModelField from './ModelField';
import MoneyField from './MoneyField';
import RegionField from './RegionField';
import TestConnectionButton from './TestConnectionButton';

// a money field laid out as a grid column (the group is carried by the grid)
const moneyColumn = (
  fieldName: string,
  label: string
): Omit<InputProps, 'group'> => ({
  custom: { Component: MoneyField },
  fieldName,
  label,
  type: InputType.Custom
});

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
      },
      {
        isDividerHidden: true,
        name: t(labelModels),
        order: 3
      },
      {
        isDividerHidden: true,
        name: t(labelPricing),
        order: 4
      },
      {
        isDividerHidden: true,
        name: t(labelSpendLimits),
        order: 5
      }
    ],
    inputs: [
      {
        fieldName: 'generalRow',
        grid: {
          columns: [
            {
              fieldName: 'name',
              label: t(labelName),
              required: true,
              type: InputType.Text
            },
            {
              fieldName: 'isActivated',
              // at most one provider is enabled: enabling this one disables
              // the other
              label: t(labelEnabled),
              type: InputType.Switch
            }
          ],
          gridTemplateColumns: '2fr 1fr'
        },
        group: t(labelGeneralInformation),
        label: '',
        // layout-only flag: keeps this row above the required Type radio,
        // since the Form sorts required inputs first within a group
        required: true,
        type: InputType.Grid
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
        fieldName: 'description',
        group: t(labelGeneralInformation),
        label: t(labelDescription),
        text: {
          multilineRows: 2
        },
        type: InputType.Text
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
        // Bedrock exposes only its AWS region; RegionField derives the
        // OpenAI-compatible endpoint URL stored in the same endpointUrl field
        custom: { Component: RegionField },
        fieldName: 'endpointUrl',
        group: t(labelConnection),
        hideInput: (values) => !equals(values.type, AiProviderType.Bedrock),
        label: t(labelAwsRegion),
        required: true,
        type: InputType.Custom
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
        custom: { Component: TestConnectionButton },
        fieldName: 'testConnection',
        group: t(labelConnection),
        label: '',
        type: InputType.Custom
      },
      {
        custom: { Component: ModelField },
        fieldName: 'defaultModel',
        group: t(labelModels),
        label: t(labelDefaultModel),
        required: true,
        type: InputType.Custom
      },
      {
        custom: { Component: ModelField },
        fieldName: 'fastModel',
        group: t(labelModels),
        label: t(labelFastModel),
        type: InputType.Custom
      },
      {
        // lists the provider's models into the fields above; uses the token
        // from the Connection section
        custom: { Component: FetchModelsButton },
        fieldName: 'fetchModels',
        group: t(labelModels),
        label: '',
        type: InputType.Custom
      },
      {
        custom: { Component: FetchPricesButton },
        fieldName: 'fetchPrices',
        group: t(labelPricing),
        label: '',
        type: InputType.Custom
      },
      {
        custom: { Component: CurrencyField },
        fieldName: 'currency',
        group: t(labelPricing),
        label: t(labelCurrency),
        type: InputType.Custom
      },
      {
        custom: { Component: CurrencyChangeWarning },
        fieldName: 'currencyChangeWarning',
        group: t(labelPricing),
        label: '',
        type: InputType.Custom
      },
      {
        fieldName: 'defaultModelPricesRow',
        grid: {
          columns: [
            moneyColumn('priceDefaultInput', t(labelDefaultModelInputPrice)),
            moneyColumn('priceDefaultOutput', t(labelDefaultModelOutputPrice))
          ]
        },
        group: t(labelPricing),
        label: '',
        type: InputType.Grid
      },
      {
        fieldName: 'fastModelPricesRow',
        grid: {
          columns: [
            moneyColumn('priceFastInput', t(labelFastModelInputPrice)),
            moneyColumn('priceFastOutput', t(labelFastModelOutputPrice))
          ]
        },
        group: t(labelPricing),
        label: '',
        type: InputType.Grid
      },
      {
        fieldName: 'limitsRow',
        grid: {
          columns: [
            moneyColumn('limitDaily', t(labelDailyLimit)),
            moneyColumn('limitMonthly', t(labelMonthlyLimit))
          ]
        },
        group: t(labelSpendLimits),
        label: '',
        type: InputType.Grid
      }
    ]
  };
};
