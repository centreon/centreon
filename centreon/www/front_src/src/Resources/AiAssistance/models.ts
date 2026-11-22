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
  // null when the model gave none; an absent key is tolerated too
  scope?: TranslateResourceFilterScope | null;
}

export interface ExplainResourceResult {
  explanation: string;
  model: string;
}

export interface SummarizeIncidentResult {
  model: string;
  summary: string;
}

export interface CorrelateAlertsResult {
  analysis: string;
  model: string;
}

export interface ExplainGraphResult {
  explanation: string;
  model: string;
}

// What the result dialog shows, whatever the feature.
export interface AiResult {
  content: string;
  model: string;
}
