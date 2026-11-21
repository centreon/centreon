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

interface Props {
  availability?: Availability;
  // What the resource filter translation answers.
  filterTranslation?: object;
  // The number of timeline events in the summarized window.
  timelineTotal?: number;
}

const initialize = (
  component: JSX.Element,
  {
    availability = { is_available: true },
    filterTranslation,
    timelineTotal = 2
  }: Props = {}
): ReturnType<typeof createStore> => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  interceptAPIRequest({
    alias: 'getAiAvailability',
    method: Method.GET,
    path: aiAvailabilityEndpoint,
    response: availability
  });

  if (filterTranslation) {
    interceptAPIRequest({
      alias: 'translateFilter',
      method: Method.POST,
      path: aiResourceFilterEndpoint,
      response: filterTranslation
    });
  }

  interceptAPIRequest({
    alias: 'correlateAlerts',
    method: Method.POST,
    path: aiAlertCorrelationEndpoint,
    response: correlationResponse
  });

  interceptAPIRequest({
    alias: 'explainResource',
    method: Method.POST,
    path: aiResourceExplanationEndpoint,
    response: resourceExplanationResponse
  });

  // The resource's timeline link is requested as is, without the API prefix.
  interceptAPIRequest({
    alias: 'listTimeline',
    method: Method.GET,
    path: `**${timelineEndpoint}`,
    response: getTimelineListing(timelineTotal)
  });

  interceptAPIRequest({
    alias: 'summarizeIncident',
    method: Method.POST,
    path: aiIncidentSummaryEndpoint,
    response: incidentSummaryResponse
  });

  interceptAPIRequest({
    alias: 'explainGraph',
    method: Method.POST,
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
