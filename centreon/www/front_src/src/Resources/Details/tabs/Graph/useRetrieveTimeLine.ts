import {
  type ListingModel,
  type Parameters,
  useFetchQuery
} from '@centreon/ui';
import { useAtomValue } from 'jotai';
import { path } from 'ramda';
import { useCallback } from 'react';
import { graphOptionsAtom } from '../../../Graph/Performance/ExportableGraphWithTimeline/graphOptionsAtoms';
import { GraphOptionId } from '../../../Graph/Performance/models';
import { buildListTimelineEventsEndpoint } from '../Timeline/api';
import { listTimelineEventsDecoder } from '../Timeline/api/decoders';
import type { TimelineEvent } from '../Timeline/models';

interface Props {
  timelineEndpoint?: string;
  graphTimeParameters?: Parameters;
}

const useRetrieveTimeLine = ({
  timelineEndpoint,
  graphTimeParameters
}: Props) => {
  const graphOptions = useAtomValue(graphOptionsAtom);

  const { start, end, timelineEventsLimit } = graphTimeParameters || {};

  const displayEventAnnotations = path<boolean>(
    [GraphOptionId.displayEvents, 'value'],
    graphOptions
  );

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
        endpoint: timelineEndpoint as string,
        parameters
      }),
    getQueryKey: () => ['timeLineEvents'],
    queryOptions: {
      enabled:
        !!timelineEndpoint && !!displayEventAnnotations && !!start && !!end,

      suspense: false
    }
  });

  const getData = useCallback(() => {
    if (!data) {
      return;
    }
    return data.result;
  }, [data]);

  return displayEventAnnotations ? getData() : [];
};

export default useRetrieveTimeLine;
