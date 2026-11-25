import {
  Group,
  InputProps,
  InputPropsWithoutGroup,
  InputType
} from '@centreon/ui';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  getBlock,
  stackedColumns,
  twoColumnsFromNarrow
} from '../../ResourceForm/grid';
import { AiProviderType } from '../models';
import {
  labelApiToken,
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
  labelReasoning,
  labelSpendLimits,
  labelType
} from '../translatedLabels';
import ApiTokenField from './ApiTokenField';
import CurrencyChangeWarning from './CurrencyChangeWarning';
import CurrencyField from './CurrencyField';
import FetchModelsButton from './FetchModelsButton';
import FetchPricesButton from './FetchPricesButton';
import ModelField from './ModelField';
import MoneyField from './MoneyField';
import ReasoningField from './ReasoningField';
import ReasoningHint from './ReasoningHint';
import RegionField from './RegionField';
import TestConnectionButton from './TestConnectionButton';
import TypeField from './TypeField';

export const idPrefix = 'ai-provider-form';

// The name takes most of the row, the switch the rest.
const nameAndStatusColumns = 'grid-cols-[2fr_1fr] gap-x-8';

interface Props {
  canEdit: boolean;
}

interface FormInputsState {
  groups: Array<Group>;
  inputs: Array<InputProps>;
}

// Each section is one block whose fields are listed in display order: the
// Form sorts required inputs first within a group, a single block leaves it
// nothing to reorder.
export const useFormInputs = ({ canEdit }: Props): FormInputsState => {
  const { t } = useTranslation();

  const getDisabled = (): boolean => !canEdit;
  // these actions call the provider with the configuration being edited: the
  // API requires the right to update it
  const hideWithoutWriteAccess = (): boolean => !canEdit;

  const money = (fieldName: string, label: string): InputPropsWithoutGroup => ({
    custom: { Component: MoneyField },
    fieldName,
    getDisabled,
    label: t(label),
    type: InputType.Custom
  });

  const pair = (
    name: string,
    columns: Array<InputPropsWithoutGroup>
  ): InputPropsWithoutGroup =>
    getBlock({ className: twoColumnsFromNarrow, columns, idPrefix, name });

  const section = (
    group: string,
    name: string,
    columns: Array<InputPropsWithoutGroup>
  ): InputProps => ({
    ...getBlock({ className: stackedColumns, columns, idPrefix, name }),
    group: t(group)
  });

  return {
    groups: [
      { isDividerHidden: true, name: t(labelGeneralInformation), order: 1 },
      { isDividerHidden: true, name: t(labelConnection), order: 2 },
      { isDividerHidden: true, name: t(labelModels), order: 3 },
      { isDividerHidden: true, name: t(labelPricing), order: 4 },
      { isDividerHidden: true, name: t(labelSpendLimits), order: 5 }
    ],
    inputs: [
      section(labelGeneralInformation, 'general', [
        getBlock({
          className: nameAndStatusColumns,
          columns: [
            {
              dataTestId: `${idPrefix}-name`,
              fieldName: 'name',
              getDisabled,
              label: t(labelName),
              required: canEdit,
              type: InputType.Text
            },
            {
              dataTestId: `${idPrefix}-is-activated`,
              fieldName: 'isActivated',
              getDisabled,
              // at most one provider is enabled: enabling this one disables
              // the other
              label: t(labelEnabled),
              type: InputType.Switch
            }
          ],
          idPrefix,
          name: 'name-and-status'
        }),
        {
          // a dropdown rather than a radio row: it stays compact in the side
          // panel and scales as providers are added. TypeField keeps the
          // stored value a plain enum string and defaults the name and models
          // on change.
          custom: { Component: TypeField },
          fieldName: 'type',
          getDisabled,
          label: t(labelType),
          required: canEdit,
          type: InputType.Custom
        },
        {
          dataTestId: `${idPrefix}-description`,
          fieldName: 'description',
          getDisabled,
          label: t(labelDescription),
          text: { multilineRows: 2 },
          type: InputType.Text
        }
      ]),
      section(labelConnection, 'connection', [
        {
          dataTestId: `${idPrefix}-endpoint-url`,
          fieldName: 'endpointUrl',
          getDisabled,
          // Bedrock has its region field below; the endpoint of the other
          // types is enforced by the API
          hideInput: (values) =>
            !equals(values.type, AiProviderType.OpenAiCompatible),
          label: t(labelEndpointUrl),
          required: canEdit,
          type: InputType.Text
        },
        {
          // Bedrock exposes only its AWS region; RegionField derives the
          // endpoint URL stored in the same endpointUrl field
          custom: { Component: RegionField },
          fieldName: 'endpointUrl',
          getDisabled,
          hideInput: (values) => !equals(values.type, AiProviderType.Bedrock),
          label: t(labelAwsRegion),
          required: canEdit,
          type: InputType.Custom
        },
        {
          // ApiTokenField masks the stored token in edit mode (see its note)
          custom: { Component: ApiTokenField },
          fieldName: 'apiToken',
          getDisabled,
          label: t(labelApiToken),
          required: canEdit,
          type: InputType.Custom
        },
        {
          custom: { Component: TestConnectionButton },
          fieldName: 'testConnection',
          hideInput: hideWithoutWriteAccess,
          // the grid keys its columns by label: each one is unique
          label: `${idPrefix}-testConnection`,
          type: InputType.Custom
        }
      ]),
      section(labelModels, 'models', [
        {
          // lists the provider's models into the fields below; uses the token
          // from the Connection section
          custom: { Component: FetchModelsButton },
          fieldName: 'fetchModels',
          hideInput: hideWithoutWriteAccess,
          label: `${idPrefix}-fetchModels`,
          type: InputType.Custom
        },
        // each model with its reasoning setting under it, side by side from
        // 600px, stacked in that order below
        pair('models-row', [
          getBlock({
            columns: [
              {
                custom: { Component: ModelField },
                fieldName: 'defaultModel',
                getDisabled,
                label: t(labelDefaultModel),
                required: canEdit,
                type: InputType.Custom
              },
              {
                custom: { Component: ReasoningField },
                dataTestId: `${idPrefix}-default-model-reasoning`,
                fieldName: 'defaultModelReasoning',
                getDisabled,
                label: t(labelReasoning),
                type: InputType.Custom
              }
            ],
            idPrefix,
            name: 'default-model'
          }),
          getBlock({
            columns: [
              {
                custom: { Component: ModelField },
                fieldName: 'fastModel',
                getDisabled,
                label: t(labelFastModel),
                type: InputType.Custom
              },
              {
                custom: { Component: ReasoningField },
                dataTestId: `${idPrefix}-fast-model-reasoning`,
                fieldName: 'fastModelReasoning',
                getDisabled,
                // without a fast model, the default model's setting applies
                hideInput: (values) => !values.fastModel,
                label: t(labelReasoning),
                type: InputType.Custom
              }
            ],
            idPrefix,
            name: 'fast-model'
          })
        ]),
        {
          custom: { Component: ReasoningHint },
          fieldName: 'reasoningHint',
          label: `${idPrefix}-reasoningHint`,
          type: InputType.Custom
        }
      ]),
      section(labelPricing, 'pricing', [
        {
          custom: { Component: FetchPricesButton },
          fieldName: 'fetchPrices',
          hideInput: hideWithoutWriteAccess,
          label: `${idPrefix}-fetchPrices`,
          type: InputType.Custom
        },
        {
          custom: { Component: CurrencyField },
          fieldName: 'currency',
          getDisabled,
          label: t(labelCurrency),
          type: InputType.Custom
        },
        {
          custom: { Component: CurrencyChangeWarning },
          fieldName: 'currencyChangeWarning',
          label: `${idPrefix}-currencyChangeWarning`,
          type: InputType.Custom
        },
        pair('default-model-prices', [
          money('priceDefaultInput', labelDefaultModelInputPrice),
          money('priceDefaultOutput', labelDefaultModelOutputPrice)
        ]),
        pair('fast-model-prices', [
          money('priceFastInput', labelFastModelInputPrice),
          money('priceFastOutput', labelFastModelOutputPrice)
        ])
      ]),
      section(labelSpendLimits, 'spend-limits', [
        pair('limits', [
          money('limitDaily', labelDailyLimit),
          money('limitMonthly', labelMonthlyLimit)
        ])
      ])
    ]
  };
};
