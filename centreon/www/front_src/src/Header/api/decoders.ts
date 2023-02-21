import { JsonDecoder } from 'ts.data.json';
import type { FromDecoder } from 'ts.data.json';

import type { PollersIssuesList, Alert, AlertDetails } from './models';

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
const AlertDetailPollerJsonDecoder = JsonDecoder.object<AlertDetails['poller']>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    since: JsonDecoder.oneOf<string | number>(
      [JsonDecoder.string, JsonDecoder.number],
      'string | number'
    )
  },
  'alert detail poller'
);

const AlertDetailDecoder = JsonDecoder.object<AlertDetails>(
  {
    poller: AlertDetailPollerJsonDecoder,
    total: JsonDecoder.number
  },
  'alert detail'
);

const issueDecoder = JsonDecoder.object<Alert>(
  {
    critical: JsonDecoder.optional(AlertDetailDecoder),
    total: JsonDecoder.number,
    warning: JsonDecoder.optional(AlertDetailDecoder)
  },
  'issue'
);

const issuesDecoder = JsonDecoder.object(
  {
    database: JsonDecoder.optional(issueDecoder),
    latency: JsonDecoder.optional(issueDecoder),
    stability: JsonDecoder.optional(issueDecoder)
  },
  'issues'
);

export const pollerIssuesDecoder = JsonDecoder.object<PollersIssuesList>(
  {
    issues: issuesDecoder,
    refreshTime: JsonDecoder.number,
    total: JsonDecoder.number
  },
  'poller issues'
);
