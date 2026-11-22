import { ResourceDetails } from '../../Details/models';
import { Resource, ResourceListing } from '../../models';

export const model = 'claude-sonnet-4-6';

export const filterTranslationResponse = {
  intent: 'filter',
  query: 'type:service status:critical',
  scope: null
};

export const emptyFilterTranslationResponse = {
  intent: 'filter',
  query: '',
  scope: null
};

export const explainCurrentViewResponse = {
  intent: 'explain',
  query: '',
  scope: 'current'
};

export const correlationResponse = {
  analysis: '## Likely root cause\n\n- The host db-server-01 is down.',
  model
};

export const resourceExplanationResponse = {
  explanation: '## What this means\n\n- The ping check lost all packets.',
  model
};

export const summary =
  '## Summary\n\n- Ping lost all packets and was acknowledged.';

export const incidentSummaryResponse = { model, summary };

export const graphExplanationResponse = {
  explanation: '## Reading\n\n- Round-trip time is stable around 5ms.',
  model
};

// The resources the Ask AI explanation runs on: what the listing displays.
export const displayedListing = {
  meta: { limit: 30, page: 1, total: 2 },
  result: [
    {
      name: 'Ping',
      parent: { name: 'db-server-01' },
      status: { name: 'CRITICAL', severity_code: 1 },
      type: 'service'
    },
    {
      name: 'db-server-01',
      status: { name: 'DOWN', severity_code: 1 },
      type: 'host'
    }
  ]
} as unknown as ResourceListing;

export const timelineEndpoint = '/monitoring/hosts/1/services/1/timeline';

export const incidentDetails = {
  duration: '2h 31m',
  information: 'CRITICAL - Packet loss = 100%',
  links: { endpoints: { timeline: timelineEndpoint } },
  name: 'Ping',
  parent: { id: 1, name: 'db-server-01' },
  status: { name: 'CRITICAL', severity_code: 1 },
  type: 'service'
} as unknown as ResourceDetails;

// Newest first, as the timeline lists them.
export const getTimelineListing = (total: number): object => ({
  meta: { limit: 100, page: 1, search: {}, sort_by: {}, total },
  result: [
    {
      contact: { name: 'admin' },
      content: 'Looking into it',
      date: '2026-06-12T08:10:00+02:00',
      id: 2,
      type: 'acknowledgement'
    },
    {
      content: 'Packet loss = 100%',
      date: '2026-06-12T08:00:00+02:00',
      id: 1,
      status: { name: 'CRITICAL', severity_code: 1 },
      tries: 3,
      type: 'event'
    }
  ]
});

export const selectedResources = [
  {
    duration: '2h 31m',
    information: 'CRITICAL - Packet loss = 100%',
    name: 'Ping',
    parent: { name: 'db-server-01' },
    status: { name: 'CRITICAL', severity_code: 1 },
    type: 'service'
  },
  {
    information: 'Host unreachable',
    name: 'db-server-01',
    status: { name: 'DOWN', severity_code: 1 },
    type: 'host'
  }
] as unknown as Array<Resource>;

export const graphDetails = {
  name: 'Ping',
  parent: { id: 1, name: 'db-server-01' },
  performance_data: 'rta=5ms;200;500;0; pl=0%;20;60;0;100',
  type: 'service'
} as unknown as ResourceDetails;

export const graphMetrics = [
  {
    average: 6.5,
    current: 5,
    maximum: 12,
    minimum: 2,
    name: 'Round-Trip-Time Average (ms)',
    unit: 'ms'
  }
];

export const aiErrorMessage = 'The AI provider did not answer';

export const getErrorResponse = (code: number): object => ({
  code,
  message: aiErrorMessage
});

// A page of the listing of the derived filter: the Ask AI explanation runs on
// the first one.
export const getDerivedListing = (page = 1): ResourceListing =>
  ({
    meta: { limit: 30, page, total: 31 },
    result: [
      {
        name: page === 1 ? 'Disk-/var' : 'Disk-/home',
        parent: { name: 'db-server-01' },
        status: { name: 'CRITICAL', severity_code: 1 },
        type: 'service'
      }
    ]
  }) as unknown as ResourceListing;

export const derivedQuery = 'status:critical';

export const explainDerivedQueryResponse = {
  intent: 'explain',
  query: derivedQuery,
  scope: 'query'
};

export const unsupportedResponse = {
  intent: 'unsupported',
  query: '',
  scope: null
};
