export enum AiProviderType {
  // the declaration order drives the display order: the curated providers
  // first, then Bedrock, with the catch-all OpenAI-compatible type last
  Anthropic = 'anthropic',
  Mistral = 'mistral',
  OpenAi = 'openai',
  Google = 'google',
  Bedrock = 'bedrock',
  OpenAiCompatible = 'openai_compatible'
}

export interface AiProviderListItem {
  id: number;
  name: string;
  type: AiProviderType;
  endpointUrl: string;
  defaultModel: string;
  fastModel?: string | null;
  description?: string | null;
  isActivated: boolean;
  currency?: string | null;
  limitDaily?: number | null;
  limitMonthly?: number | null;
  usedDaily?: number | null;
  usedMonthly?: number | null;
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

// A provider as the API returns it: the API token is write-only.
export interface AiProvider extends Billing {
  name: string;
  type: AiProviderType;
  endpointUrl: string;
  defaultModel: string;
  fastModel?: string | null;
  description?: string | null;
  isActivated: boolean;
}

// What the form edits: a provider, plus the token to send (empty to keep the
// stored one).
export interface AiProviderFormValues extends AiProvider {
  apiToken?: string;
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
  type?: AiProviderType;
}

export interface Filters {
  name: string;
  type: Array<AiProviderType>;
}
