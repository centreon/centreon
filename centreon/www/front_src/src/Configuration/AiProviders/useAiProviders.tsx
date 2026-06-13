import { Method } from '@centreon/ui';

import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { APIType, FieldType, FilterConfiguration } from '../models';
import {
  aiProviderDecoder,
  aiProvidersEndpoint,
  aiProvidersListDecoder,
  getAiProviderEndpoint
} from './api';
import { AiProvider, AiProviderType, Payload } from './models';
import { labelName, labelType } from './translatedLabels';
import { aiProviderTypeLabels } from './utils';

interface UseAiProvidersState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

// the price/limit fields are optional numbers: an empty or cleared field is null
const toNumber = (value: number | string | null | undefined): number | null => {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const parsed = Number(value);

  return Number.isNaN(parsed) ? null : parsed;
};

export const adaptFormToApiPayload = (data: unknown): Payload => {
  const formData = data as AiProvider;

  return {
    currency: formData.currency || null,
    default_model: formData.defaultModel,
    description: formData.description || null,
    fast_model: formData.fastModel || null,
    is_activated: formData.isActivated,
    limit_daily: toNumber(formData.limitDaily),
    limit_monthly: toNumber(formData.limitMonthly),
    name: formData.name,
    price_default_input: toNumber(formData.priceDefaultInput),
    price_default_output: toNumber(formData.priceDefaultOutput),
    price_fast_input: toNumber(formData.priceFastInput),
    price_fast_output: toNumber(formData.priceFastOutput),
    type: formData.type,
    // the token is write-only: an empty field means "keep the current token"
    ...(formData.apiToken ? { api_token: formData.apiToken } : {}),
    // only meaningful for OpenAI-compatible providers: the API enforces
    // the official endpoint of the other types
    ...(formData.endpointUrl ? { endpoint_url: formData.endpointUrl } : {})
  };
};

const useAiProviders = (): UseAiProvidersState => {
  const { t } = useTranslation();

  const api: APIType = useMemo(
    () => ({
      adapter: adaptFormToApiPayload,
      apiFormat: 'JSON-LD',
      decoders: {
        getAll: aiProvidersListDecoder,
        getOne: aiProviderDecoder
      },
      endpoints: {
        create: aiProvidersEndpoint,
        deleteOne: getAiProviderEndpoint,
        disable: (params) => getAiProviderEndpoint({ id: params?.id ?? '' }),
        enable: (params) => getAiProviderEndpoint({ id: params?.id ?? '' }),
        getAll: aiProvidersEndpoint,
        getOne: getAiProviderEndpoint,
        update: getAiProviderEndpoint
      },
      methods: {
        disable: Method.PATCH,
        enable: Method.PATCH,
        update: Method.PATCH
      }
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = [
    {
      fieldName: 'name',
      fieldType: FieldType.Text,
      name: t(labelName)
    },
    {
      fieldName: 'type',
      fieldType: FieldType.Checkboxes,
      name: t(labelType),
      options: Object.values(AiProviderType).map((type) => ({
        id: type,
        name: t(aiProviderTypeLabels[type])
      }))
    }
  ];

  return {
    api,
    filtersConfiguration
  };
};

export default useAiProviders;
