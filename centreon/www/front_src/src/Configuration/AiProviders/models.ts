export enum AiProviderType {
  // the declaration order drives the display order: alphabetical,
  // with the catch-all OpenAI-compatible type last
  Anthropic = 'anthropic',
  Mistral = 'mistral',
  OpenAi = 'openai',
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
  isActivated: boolean;
}

export interface Billing {
  currency?: string | null;
  limitDaily?: number | null;
  limitMonthly?: number | null;
  priceDefaultInput?: number | null;
  priceDefaultOutput?: number | null;
  priceFastInput?: number | null;
  priceFastOutput?: number | null;
}

export interface AiProvider extends Billing {
  name: string;
  type: string;
  endpointUrl: string;
  apiToken?: string;
  defaultModel: string;
  fastModel?: string | null;
  description?: string | null;
  isActivated: boolean;
}

export interface Payload {
  currency: string | null;
  default_model: string;
  description: string | null;
  fast_model: string | null;
  is_activated: boolean;
  limit_daily: number | null;
  limit_monthly: number | null;
  name: string;
  price_default_input: number | null;
  price_default_output: number | null;
  price_fast_input: number | null;
  price_fast_output: number | null;
  api_token?: string;
  endpoint_url?: string;
  type?: string;
}

export interface Filters {
  name: string;
  type: Array<AiProviderType>;
}
