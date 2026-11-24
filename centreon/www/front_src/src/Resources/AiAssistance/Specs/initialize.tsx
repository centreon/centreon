import { SnackbarProvider } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import {
  aiAlertCorrelationEndpoint,
  aiAvailabilityEndpoint,
  aiGraphExplanationEndpoint,
  aiIncidentSummaryEndpoint,
  aiResourceExplanationEndpoint,
  aiResourceFilterEndpoint
} from '../api/endpoints';
import {
  correlationResponse,
  getErrorResponse,
  getTimelineListing,
  graphExplanationResponse,
  incidentSummaryResponse,
  resourceExplanationResponse,
  timelineEndpoint
} from './utils';

interface Availability {
  is_available: boolean;
  reason?: string | null;
}

type AiCall =
  | 'correlateAlerts'
  | 'explainGraph'
  | 'explainResource'
  | 'summarizeIncident'
  | 'translateFilter';

interface Props {
  availability?: Availability;
  // The status the availability endpoint answers with.
  availabilityStatus?: number;
  // The AI calls that fail, with the status they answer.
  failures?: Partial<Record<AiCall, number>>;
  // What the resource filter translation answers.
  filterTranslation?: object;
  // The number of timeline events in the last 24 hours, as counted for the
  // summary action.
  recentTimelineTotal?: number;
  // What the AI calls answer, when not the default response.
  responses?: Partial<Record<AiCall, object>>;
  // The status the listing of the summarized window answers with.
  timelineStatus?: number;
  // The number of timeline events in the summarized window.
  timelineTotal?: number;
}

const initialize = (
  component: JSX.Element,
  {
    availability = { is_available: true },
    availabilityStatus = 200,
    failures = {},
    filterTranslation,
    recentTimelineTotal,
    responses = {},
    timelineStatus = 200,
    timelineTotal = 2
  }: Props = {}
): ReturnType<typeof createStore> => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const interceptAiCall = ({
    alias,
    path,
    response
  }: {
    alias: AiCall;
    path: string;
    response?: object;
  }): void => {
    const statusCode = failures[alias];

    interceptAPIRequest({
      alias,
      method: Method.POST,
      path,
      response: statusCode
        ? getErrorResponse(statusCode)
        : (responses[alias] ?? response),
      statusCode
    });
  };

  interceptAPIRequest({
    alias: 'getAiAvailability',
    method: Method.GET,
    path: aiAvailabilityEndpoint,
    response:
      availabilityStatus === 200
        ? availability
        : getErrorResponse(availabilityStatus),
    statusCode: availabilityStatus
  });

  if (filterTranslation || failures.translateFilter) {
    interceptAiCall({
      alias: 'translateFilter',
      path: aiResourceFilterEndpoint,
      response: filterTranslation
    });
  }

  interceptAiCall({
    alias: 'correlateAlerts',
    path: aiAlertCorrelationEndpoint,
    response: correlationResponse
  });

  interceptAiCall({
    alias: 'explainResource',
    path: aiResourceExplanationEndpoint,
    response: resourceExplanationResponse
  });

  // The resource's timeline link is requested as is, without the API prefix.
  interceptAPIRequest({
    alias: 'listTimeline',
    method: Method.GET,
    path: `**${timelineEndpoint}`,
    response:
      timelineStatus === 200
        ? getTimelineListing(timelineTotal)
        : getErrorResponse(timelineStatus),
    statusCode: timelineStatus
  });

  // The count of the recent events, which enables the summary action: a
  // single event is requested. Registered last, so it is matched first.
  interceptAPIRequest({
    alias: 'countRecentTimelineEvents',
    method: Method.GET,
    path: `**${timelineEndpoint}`,
    query: { name: 'limit', value: '1' },
    response: getTimelineListing(recentTimelineTotal ?? timelineTotal)
  });

  interceptAiCall({
    alias: 'summarizeIncident',
    path: aiIncidentSummaryEndpoint,
    response: incidentSummaryResponse
  });

  interceptAiCall({
    alias: 'explainGraph',
    path: aiGraphExplanationEndpoint,
    response: graphExplanationResponse
  });

  const store = createStore();

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  render(
    <SnackbarProvider>
      <QueryClientProvider client={queryClient}>
        <Provider store={store}>{component}</Provider>
      </QueryClientProvider>
    </SnackbarProvider>
  );

  return store;
};

export default initialize;
