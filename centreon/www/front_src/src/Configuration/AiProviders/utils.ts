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

interface DefaultModels {
  defaultModel: string | null;
  fastModel: string | null;
}

// sensible, editable defaults prefilled by the form on type selection;
// the models of OpenAI-compatible providers can not be known in advance
export const defaultModels: Record<AiProviderType, DefaultModels> = {
  [AiProviderType.Anthropic]: {
    defaultModel: 'claude-sonnet-4-6',
    fastModel: 'claude-haiku-4-5'
  },
  [AiProviderType.Mistral]: {
    defaultModel: 'mistral-medium-3-5',
    fastModel: 'mistral-small-latest'
  },
  [AiProviderType.OpenAi]: {
    defaultModel: 'gpt-5.4',
    fastModel: 'gpt-5.4-mini'
  },
  [AiProviderType.OpenAiCompatible]: {
    defaultModel: null,
    fastModel: null
  }
};
