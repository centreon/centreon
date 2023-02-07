import { JsonDecoder } from 'ts.data.json';
import type { FromDecoder } from 'ts.data.json';

const counterDecoder = JsonDecoder.object(
  {
    total: JsonDecoder.string,
    unhandled: JsonDecoder.string
  },
  'counter'
);

export const serviceStatusDecoder = JsonDecoder.object(
  {
    critical: counterDecoder,
    ok: JsonDecoder.string,
    pending: JsonDecoder.string,
    refreshTime: JsonDecoder.number,
    time: JsonDecoder.number,
    total: JsonDecoder.number,
    unknown: counterDecoder,
    warning: counterDecoder
  },
  'service status'
);

export type ServiceStatusResponse = FromDecoder<typeof serviceStatusDecoder>;

export const hostStatusDecoder = JsonDecoder.object(
  {
    down: counterDecoder,
    ok: JsonDecoder.string,
    pending: JsonDecoder.string,
    refreshTime: JsonDecoder.number,
    time: JsonDecoder.number,
    total: JsonDecoder.number,
    unreachable: counterDecoder
  },
  'host status'
);

export type HostStatusResponse = FromDecoder<typeof hostStatusDecoder>;

const issueDecoder = JsonDecoder.object(
  {
    critical: JsonDecoder.number,
    total: JsonDecoder.number,
    warning: JsonDecoder.number
  },
  'issue'
);

export const pollerIssuesDecoder = JsonDecoder.object(
  {
    database: JsonDecoder.optional(issueDecoder),
    latency: JsonDecoder.optional(issueDecoder),
    refreshTime: JsonDecoder.number,
    stability: JsonDecoder.optional(issueDecoder),
    total: JsonDecoder.number
  },
  'poller issues'
);

export type PollerIssuesResponse = FromDecoder<typeof pollerIssuesDecoder>;
