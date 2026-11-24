import { Resource } from '../models';

// The alert-correlation endpoint accepts at most this many resources.
export const maxCorrelatedResources = 50;

// The graph-explanation endpoint accepts at most this many metrics: the first
// ones, in the graph's order, are sent.
export const maxGraphMetrics = 50;

// The longest check output, command line or performance data the AI
// endpoints accept.
export const maxTextLength = 10_000;

// Cut by code point rather than by UTF-16 unit: the API counts characters,
// and a cut through a surrogate pair would send invalid UTF-16.
export const truncate = (
  value: string | null | undefined,
  maxLength = maxTextLength
): string | null => {
  if (value === null || value === undefined) {
    return null;
  }

  return value.length <= maxLength
    ? value
    : Array.from(value).slice(0, maxLength).join('');
};

// The resources most worth analyzing when there are too many: the most severe
// statuses first, the given order kept within a status.
export const selectMostSevere = (
  resources: Array<Resource>
): Array<Resource> =>
  resources.length <= maxCorrelatedResources
    ? resources
    : [...resources]
        .sort(
          (a, b) =>
            (a.status?.severity_code ?? Number.POSITIVE_INFINITY) -
            (b.status?.severity_code ?? Number.POSITIVE_INFINITY)
        )
        .slice(0, maxCorrelatedResources);

// React escapes the rendered text already: escaping the interpolated values
// too would show a "/" in a resource or model name as "&#x2F;".
export const unescaped = { interpolation: { escapeValue: false } };
