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
    defaultModel: 'claude-sonnet-5-5',
    fastModel: 'claude-haiku-5-5'
  },
  // Mistral Medium is retired (its replacement is Large 4), so the general
  // model is now Large. The -latest aliases track the current version; only the
  // optional price prefill may lag behind them, and prices can be set by hand.
  [AiProviderType.Mistral]: {
    defaultModel: 'mistral-large-latest',
    fastModel: 'mistral-small-latest'
  },
  [AiProviderType.OpenAi]: {
    defaultModel: 'gpt-6.2-sol',
    fastModel: 'gpt-6-luna'
  },
  [AiProviderType.Google]: {
    defaultModel: 'gemini-4-hydrogen',
    fastModel: 'gemini-3.5-flash-lite'
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

// The stored API token is only sent to the endpoint it was configured for
// (the API enforces it): a new endpoint requires typing the token again. A
// trailing slash does not change the endpoint.
const normalizeEndpoint = (endpointUrl?: string | null): string =>
  (endpointUrl ?? '').replace(/\/+$/, '');

export const isSameEndpoint = (
  endpointUrl?: string | null,
  storedEndpointUrl?: string | null
): boolean =>
  normalizeEndpoint(endpointUrl) === normalizeEndpoint(storedEndpointUrl);
