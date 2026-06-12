import { AiProviderType, Filters } from './models';
import {
  labelAnthropic,
  labelMistral,
  labelOpenAi,
  labelOpenAiCompatible
} from './translatedLabels';

export const defaultSelectedColumnIds = [
  'name',
  'type',
  'endpoint_url',
  'default_model',
  'is_default',
  'actions'
];

export const filtersInitialValues: Filters = {
  name: '',
  type: []
};

export const filtersAtomKey = 'centreon-ai-providers-filters-1';
export const columnsAtomKey = 'centreon-ai-providers-columns-1';

export const aiProviderTypeLabels: Record<AiProviderType, string> = {
  [AiProviderType.Anthropic]: labelAnthropic,
  [AiProviderType.OpenAi]: labelOpenAi,
  [AiProviderType.Mistral]: labelMistral,
  [AiProviderType.OpenAiCompatible]: labelOpenAiCompatible
};

export const defaultEndpointUrls: Record<AiProviderType, string | null> = {
  [AiProviderType.Anthropic]: 'https://api.anthropic.com',
  [AiProviderType.OpenAi]: 'https://api.openai.com',
  [AiProviderType.Mistral]: 'https://api.mistral.ai',
  [AiProviderType.OpenAiCompatible]: null
};
