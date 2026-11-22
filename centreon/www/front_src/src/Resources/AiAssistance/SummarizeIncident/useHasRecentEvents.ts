import { type ListingModel, useFetchQuery } from '@centreon/ui';

import { listTimelineEventsDecoder } from '../../Details/tabs/Timeline/api/decoders';
import { TimelineEvent } from '../../Details/tabs/Timeline/models';
import {
  buildTimelineWindowEndpoint,
  getWindowStart
} from './useSummarizeIncident';

interface Props {
  enabled: boolean;
  timelineEndpoint?: string;
}

// Whether the resource has events in the window the summary covers (the last
// 24 hours), rather than in the period the Timeline tab displays. null while
// unknown, which keeps the action enabled: the summary checks it again.
const useHasRecentEvents = ({
  enabled,
  timelineEndpoint
}: Props): boolean | null => {
  const { data } = useFetchQuery<ListingModel<TimelineEvent>>({
    baseEndpoint: '',
    decoder: listTimelineEventsDecoder,
    // the window starts when the request is sent, so each refetch moves it
    getEndpoint: () =>
      buildTimelineWindowEndpoint({
        endpoint: timelineEndpoint as string,
        limit: 1,
        since: getWindowStart()
      }),
    getQueryKey: () => ['aiRecentTimelineEvents', timelineEndpoint],
    // the Timeline tab already reports a failure to list the events
    httpCodesBypassErrorSnackbar: [400, 401, 403, 404, 500, 502, 503],
    queryOptions: {
      enabled: enabled && Boolean(timelineEndpoint),
      // events come and go out of the window while the tab stays open
      refetchInterval: 60_000,
      suspense: false
    }
  });

  if (!timelineEndpoint) {
    return false;
  }

  return data ? data.meta.total > 0 : null;
};

export default useHasRecentEvents;
