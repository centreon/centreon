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

export const adaptFormToApiPayload = (data: unknown): Payload => {
  const formData = data as AiProvider;

  return {
    default_model: formData.defaultModel,
    description: formData.description || null,
    fast_model: formData.fastModel || null,
    is_default: formData.isDefault,
    name: formData.name,
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
        getAll: aiProvidersEndpoint,
        getOne: getAiProviderEndpoint,
        update: getAiProviderEndpoint
      },
      methods: {
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
