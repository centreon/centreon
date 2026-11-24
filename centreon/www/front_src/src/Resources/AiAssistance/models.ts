export type AiUnavailableReason = 'no_provider' | 'budget_exhausted';

export interface AiAvailability {
  isAvailable: boolean;
  reason?: AiUnavailableReason | null;
}

export type TranslateResourceFilterIntent =
  | 'filter'
  | 'explain'
  | 'unsupported';

export type TranslateResourceFilterScope = 'current' | 'query';

export interface TranslateResourceFilterResult {
  intent: TranslateResourceFilterIntent;
  query: string;
  // what the model says an explanation covers: the listing displayed or the
  // resources of the query. null (or absent) when it said nothing, which
  // changes nothing: AskAiFilter routes on the query, not on the scope
  scope?: TranslateResourceFilterScope | null;
}

export interface ExplainResourceResult {
  explanation: string;
  model: string;
  truncated?: boolean;
}

export interface SummarizeIncidentResult {
  model: string;
  summary: string;
  truncated?: boolean;
}

export interface CorrelateAlertsResult {
  analysis: string;
  model: string;
  truncated?: boolean;
}

export interface ExplainGraphResult {
  explanation: string;
  model: string;
  truncated?: boolean;
}

// What the result dialog shows, whatever the feature.
export interface AiResult {
  content: string;
  model: string;
  // shown next to the disclaimer, e.g. what was left out of the request
  notice?: string;
  // the answer reached the length limit of the provider and was cut short
  truncated?: boolean;
}
