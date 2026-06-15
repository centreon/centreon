export interface TranslateResourceFilterResult {
  search: string;
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
