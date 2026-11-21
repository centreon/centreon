import { JsonDecoder } from 'ts.data.json';

import {
  AiAvailability,
  AiUnavailableReason,
  CorrelateAlertsResult,
  ExplainGraphResult,
  ExplainResourceResult,
  SummarizeIncidentResult,
  TranslateResourceFilterIntent,
  TranslateResourceFilterResult,
  TranslateResourceFilterScope
} from '../models';

export const aiAvailabilityDecoder = JsonDecoder.object<AiAvailability>(
  {
    isAvailable: JsonDecoder.boolean,
    reason: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.enumeration<AiUnavailableReason>(
          { budget_exhausted: 'budget_exhausted', no_provider: 'no_provider' },
          'AiUnavailableReason'
        )
      )
    )
  },
  'AiAvailability',
  {
    isAvailable: 'is_available'
  }
);

export const translateResourceFilterDecoder =
  JsonDecoder.object<TranslateResourceFilterResult>(
    {
      intent: JsonDecoder.enumeration<TranslateResourceFilterIntent>(
        { explain: 'explain', filter: 'filter', unsupported: 'unsupported' },
        'TranslateResourceFilterIntent'
      ),
      query: JsonDecoder.string,
      scope: JsonDecoder.optional(
        JsonDecoder.nullable(
          JsonDecoder.enumeration<TranslateResourceFilterScope>(
            { current: 'current', query: 'query' },
            'TranslateResourceFilterScope'
          )
        )
      )
    },
    'TranslateResourceFilterResult'
  );

export const explainResourceDecoder = JsonDecoder.object<ExplainResourceResult>(
  {
    explanation: JsonDecoder.string,
    model: JsonDecoder.string
  },
  'ExplainResourceResult'
);

export const summarizeIncidentDecoder =
  JsonDecoder.object<SummarizeIncidentResult>(
    {
      model: JsonDecoder.string,
      summary: JsonDecoder.string
    },
    'SummarizeIncidentResult'
  );

export const correlateAlertsDecoder = JsonDecoder.object<CorrelateAlertsResult>(
  {
    analysis: JsonDecoder.string,
    model: JsonDecoder.string
  },
  'CorrelateAlertsResult'
);

export const explainGraphDecoder = JsonDecoder.object<ExplainGraphResult>(
  {
    explanation: JsonDecoder.string,
    model: JsonDecoder.string
  },
  'ExplainGraphResult'
);
