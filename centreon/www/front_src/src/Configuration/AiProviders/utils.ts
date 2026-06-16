import { AiProviderType, Filters } from './models';
import {
  labelAnthropic,
  labelBedrock,
  labelGoogle,
  labelMistral,
  labelOpenAi,
  labelOpenAiCompatible
} from './translatedLabels';

export const defaultSelectedColumnIds = [
  'name',
  'type',
  'default_model',
  'usage',
  'is_activated',
  'actions'
];

export const filtersInitialValues: Filters = {
  name: '',
  type: []
};

export const filtersAtomKey = 'centreon-ai-providers-filters-1';
export const columnsAtomKey = 'centreon-ai-providers-columns-3';

export const aiProviderTypeLabels: Record<AiProviderType, string> = {
  [AiProviderType.Anthropic]: labelAnthropic,
  [AiProviderType.Mistral]: labelMistral,
  [AiProviderType.OpenAi]: labelOpenAi,
  [AiProviderType.Google]: labelGoogle,
  [AiProviderType.Bedrock]: labelBedrock,
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
    defaultModel: 'mistral-medium-latest',
    fastModel: 'mistral-small-latest'
  },
  [AiProviderType.OpenAi]: {
    defaultModel: 'gpt-5.4',
    fastModel: 'gpt-5.4-mini'
  },
  [AiProviderType.Google]: {
    defaultModel: 'gemini-3.5-flash',
    fastModel: 'gemini-3.1-flash-lite'
  },
  // Bedrock (mantle) exposes bare vendor.model ids (no region prefix) across
  // several vendors. Default to text-only Qwen3 models: they route through the
  // OpenAI-compatible surface (no Anthropic Marketplace subscription needed) and
  // are inexpensive. Users adjust to whatever their catalog lists, e.g.
  // anthropic.claude-opus-4-8 for higher capability.
  [AiProviderType.Bedrock]: {
    defaultModel: 'qwen.qwen3-235b-a22b-2507',
    fastModel: 'qwen.qwen3-32b'
  },
  [AiProviderType.OpenAiCompatible]: {
    defaultModel: null,
    fastModel: null
  }
};
