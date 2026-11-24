import {
  buildListingEndpoint,
  type ListingModel,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';

import { ResourceDetails } from '../../Details/models';
import { listTimelineEventsDecoder } from '../../Details/tabs/Timeline/api/decoders';
import { TimelineEvent } from '../../Details/tabs/Timeline/models';
import { summarizeIncidentDecoder } from '../api/decoders';
import { aiIncidentSummaryEndpoint } from '../api/endpoints';
import { useAiCall } from '../api/useAiCall';
import { SummarizeIncidentResult } from '../models';
import { labelNoRecentEvents } from '../translatedLabels';
import { truncate } from '../utils';

const maxEvents = 100;
// the summary covers this window, whatever period the Timeline tab displays
const windowInHours = 24;
const maxEventContentLength = 2000;

export const getWindowStart = (): string =>
  dayjs().subtract(windowInHours, 'hour').toISOString();

// The events of the resource since the start of the window. The resource's
// timeline link is used as is (no API prefix), as the timeline tab does.
export const buildTimelineWindowEndpoint = ({
  endpoint,
  limit,
  since
}: {
  endpoint: string;
  limit: number;
  since: string;
}): string =>
  buildListingEndpoint({
    baseEndpoint: endpoint,
    parameters: {
      limit,
      page: 1,
      search: {
        conditions: [{ field: 'date', values: { $gt: since } }]
      }
    }
  });

interface IncidentEventPayload {
  author: string | null;
  content: string | null;
  date: string;
  status: string | null;
  type: string;
}

interface SummarizeIncidentPayload {
  duration: string | null;
  events: Array<IncidentEventPayload>;
  information: string | null;
  is_truncated: boolean;
  name: string;
  parent_name: string | null;
  resource_type: string;
  since: string;
  status: string;
}

interface TimelineWindow {
  endpoint: string;
  since: string;
}

interface UseSummarizeIncidentState {
  isLoading: boolean;
  summarize: (
    details: ResourceDetails
  ) => Promise<SummarizeIncidentResult | null>;
}

const toEventPayload = (event: TimelineEvent): IncidentEventPayload => ({
  author: event.contact?.name ?? null,
  content: truncate(event.content, maxEventContentLength),
  date: event.date,
  status: event.status?.name ?? null,
  type: event.type
});

const useSummarizeIncident = (): UseSummarizeIncidentState => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  // Fetched on demand, for a window that starts when the user asks, so it is
  // sent like a mutation rather than kept as a query.
  const { mutateAsync: listEvents, isMutating: isListingEvents } =
    useMutationQuery<ListingModel<TimelineEvent>, TimelineWindow>({
      baseEndpoint: '',
      decoder: listTimelineEventsDecoder,
      getEndpoint: ({ endpoint, since }) =>
        buildTimelineWindowEndpoint({ endpoint, limit: maxEvents, since }),
      method: Method.GET
    });

  const { call, isMutating } = useAiCall<
    SummarizeIncidentPayload,
    SummarizeIncidentResult
  >({
    decoder: summarizeIncidentDecoder,
    endpoint: aiIncidentSummaryEndpoint
  });

  const summarize = async (
    details: ResourceDetails
  ): Promise<SummarizeIncidentResult | null> => {
    const timelineEndpoint = details.links?.endpoints?.timeline;

    if (!timelineEndpoint) {
      showWarningMessage(t(labelNoRecentEvents));

      return null;
    }

    const since = getWindowStart();

    const listing = await listEvents({
      _meta: { endpoint: timelineEndpoint, since }
    });

    // the error snackbar already shows why the timeline could not be listed
    if ('isError' in listing) {
      return null;
    }

    if (listing.result.length === 0) {
      showWarningMessage(t(labelNoRecentEvents));

      return null;
    }

    const events = [...listing.result]
      .sort((a, b) => dayjs(a.date).valueOf() - dayjs(b.date).valueOf())
      .map(toEventPayload);

    return call({
      duration: details.duration ?? null,
      events,
      information: truncate(details.information),
      is_truncated: listing.meta.total > maxEvents,
      name: details.name,
      parent_name: details.parent?.name ?? null,
      resource_type: details.type,
      since,
      status: details.status?.name ?? 'UNKNOWN'
    });
  };

  return { isLoading: isListingEvents || isMutating, summarize };
};

export default useSummarizeIncident;
