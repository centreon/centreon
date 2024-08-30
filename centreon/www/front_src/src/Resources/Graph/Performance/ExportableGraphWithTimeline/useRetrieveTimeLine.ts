import { type ListingModel, useFetchQuery } from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { path } from 'ramda';
import { buildListTimelineEventsEndpoint } from '../../../Details/tabs/Timeline/api';
import { listTimelineEventsDecoder } from '../../../Details/tabs/Timeline/api/decoders';
import type { TimelineEvent } from '../../../Details/tabs/Timeline/models';
import { GraphOptionId } from '../models';
import { graphOptionsAtom } from './graphOptionsAtoms';

const useRetrieveTimeLine = ({
  timelineEndpoint,
  start,
  end,
  timelineEventsLimit
}) => {
  // const timelineEndpoint = path<string>(
  //     ['links', 'endpoints', 'timeline'],
  //     resource
  //   );

  const graphOptions = useAtomValue(graphOptionsAtom);

  const displayEventAnnotations = path<boolean>(
    [GraphOptionId.displayEvents, 'value'],
    graphOptions
  );

  console.log('--->0000', graphOptions);

  const parameters = {
    limit: timelineEventsLimit,
    search: {
      conditions: [
        {
          field: 'date',
          values: {
            $gt: start,
            $lt: end
          }
        }
      ]
    }
  };
  const { data } = useFetchQuery<ListingModel<TimelineEvent>>({
    decoder: listTimelineEventsDecoder,
    getEndpoint: () =>
      buildListTimelineEventsEndpoint({
        endpoint: timelineEndpoint,
        parameters
      }),
    getQueryKey: () => ['timeLineEvents'],
    queryOptions: {
      enabled:
        !!timelineEndpoint && !!displayEventAnnotations && !!start && !!end,

      suspense: false
    }
  });

  return displayEventAnnotations ? data : [];
};

export default useRetrieveTimeLine;
