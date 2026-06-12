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
  [AiProviderType.Mistral]: labelMistral,
  [AiProviderType.OpenAi]: labelOpenAi,
  [AiProviderType.OpenAiCompatible]: labelOpenAiCompatible
};
