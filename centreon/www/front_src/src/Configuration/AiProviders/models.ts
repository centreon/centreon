export enum AiProviderType {
  Anthropic = 'anthropic',
  OpenAi = 'openai',
  Mistral = 'mistral',
  OpenAiCompatible = 'openai_compatible'
}

export interface AiProviderListItem {
  id: number;
  name: string;
  type: string;
  endpointUrl: string;
  defaultModel: string;
  fastModel?: string | null;
  description?: string | null;
  isDefault: boolean;
}

export interface AiProvider {
  name: string;
  type: string;
  endpointUrl: string;
  apiToken?: string;
  defaultModel: string;
  fastModel?: string | null;
  description?: string | null;
  isDefault: boolean;
}

export interface Payload {
  name: string;
  endpoint_url: string;
  default_model: string;
  fast_model: string | null;
  description: string | null;
  is_default: boolean;
  type?: string;
  api_token?: string;
}

export interface Filters {
  name: string;
  type: Array<AiProviderType>;
}
