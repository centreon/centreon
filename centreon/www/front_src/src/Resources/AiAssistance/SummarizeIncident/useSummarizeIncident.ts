import {
  ListingModel,
  ListingParameters,
  Method,
  useMutationQuery,
  useRequest,
  useSnackbar
} from '@centreon/ui';

import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';

import { ResourceDetails } from '../../Details/models';
import { listTimelineEvents } from '../../Details/tabs/Timeline/api';
import { listTimelineEventsDecoder } from '../../Details/tabs/Timeline/api/decoders';
import { TimelineEvent } from '../../Details/tabs/Timeline/models';
import { aiIncidentSummaryEndpoint } from '../endpoints';
import { SummarizeIncidentResult } from '../models';
import { labelNoRecentEvents } from '../translatedLabels';

const maxEvents = 100;
const windowInHours = 24;
const maxEventContentLength = 2000;

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

interface UseSummarizeIncidentState {
  isLoading: boolean;
  summarize: (
    details: ResourceDetails
  ) => Promise<SummarizeIncidentResult | null>;
}

const toEventPayload = (event: TimelineEvent): IncidentEventPayload => ({
  author: event.contact?.name ?? null,
  content: event.content
    ? event.content.substring(0, maxEventContentLength)
    : null,
  date: event.date,
  status: event.status?.name ?? null,
  type: event.type
});

const useSummarizeIncident = (): UseSummarizeIncidentState => {
  const { t } = useTranslation();
  const { showWarningMessage } = useSnackbar();

  const { sendRequest: listEvents, sending: isListingEvents } = useRequest<
    ListingModel<TimelineEvent>
  >({
    decoder: listTimelineEventsDecoder,
    request: (cancelToken) => (params) =>
      listTimelineEvents(cancelToken)(
        params as { endpoint: string; parameters: ListingParameters }
      )
  });

  const { mutateAsync, isMutating } = useMutationQuery<
    SummarizeIncidentPayload,
    undefined
  >({
    getEndpoint: () => aiIncidentSummaryEndpoint,
    method: Method.POST
  });

  const summarize = async (
    details: ResourceDetails
  ): Promise<SummarizeIncidentResult | null> => {
    const timelineEndpoint = details.links?.endpoints?.timeline;

    if (!timelineEndpoint) {
      showWarningMessage(t(labelNoRecentEvents));

      return null;
    }

    const since = dayjs().subtract(windowInHours, 'hour').toISOString();

    const listing = await listEvents({
      endpoint: timelineEndpoint,
      parameters: {
        limit: maxEvents,
        page: 1,
        search: {
          conditions: [{ field: 'date', values: { $gt: since } }]
        }
      }
    });

    if (listing.result.length === 0) {
      showWarningMessage(t(labelNoRecentEvents));

      return null;
    }

    const events = [...listing.result]
      .sort((a, b) => dayjs(a.date).valueOf() - dayjs(b.date).valueOf())
      .map(toEventPayload);

    return mutateAsync({
      payload: {
        duration: details.duration ?? null,
        events,
        information: details.information ?? null,
        is_truncated: listing.meta.total > maxEvents,
        name: details.name,
        parent_name: details.parent?.name ?? null,
        resource_type: details.type,
        since,
        status: details.status?.name ?? 'UNKNOWN'
      }
    }).then((result) => {
      if ('isError' in result) {
        return null;
      }

      return result as unknown as SummarizeIncidentResult;
    });
  };

  return { isLoading: isListingEvents || isMutating, summarize };
};

export default useSummarizeIncident;
