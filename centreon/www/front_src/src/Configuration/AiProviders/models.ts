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

// How much a model reasons before answering: as little as it allows, or as
// its provider configures it by default.
export enum ReasoningMode {
  Minimal = 'minimal',
  ModelDefault = 'default'
}

// what a successful connection test still reveals about the model
export enum ConnectionTestWarning {
  BudgetUsedWithoutAnswer = 'budget_used_without_answer',
  EmptyAnswer = 'empty_answer',
  NoReasoningSetting = 'no_reasoning_setting',
  Refused = 'refused'
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
  defaultModelReasoning: ReasoningMode;
  fastModel?: string | null;
  // applies to the fast model only when one is set: the default model's
  // setting applies otherwise
  fastModelReasoning: ReasoningMode;
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
  default_model_reasoning: ReasoningMode;
  description: string | null;
  fast_model: string | null;
  fast_model_reasoning: ReasoningMode;
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
