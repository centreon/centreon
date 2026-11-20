import { Group, InputProps, InputType } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { formStateAtom } from '../../ConfigurationBase/atoms';
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
import CurrencyChangeWarning from './CurrencyChangeWarning';
import CurrencyField from './CurrencyField';
import FetchModelsButton from './FetchModelsButton';
import FetchPricesButton from './FetchPricesButton';
import ModelField from './ModelField';
import MoneyField from './MoneyField';
import RegionField from './RegionField';
import TestConnectionButton from './TestConnectionButton';
import TypeField from './TypeField';

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

  const { mode } = useAtomValue(formStateAtom);
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
        // a dropdown rather than a radio row: it stays compact in the side
        // panel and scales as providers are added. TypeField keeps the stored
        // value a plain enum string and defaults the name/models on change.
        custom: { Component: TypeField },
        fieldName: 'type',
        group: t(labelGeneralInformation),
        label: t(labelType),
        required: true,
        type: InputType.Custom
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
