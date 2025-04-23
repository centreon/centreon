import {
  MutableRefObject,
  ReactNode,
  useEffect,
  useMemo,
  useRef,
  useState
} from 'react';

import { useAtomValue } from 'jotai';
import { path, isNil, not, or } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Paper, Theme } from '@mui/material';

import type { ListingModel } from '@centreon/ui';
import { getXAxisTickFormat, useRequest } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import PerformanceGraph from '..';
import { detailsAtom } from '../../../Details/detailsAtoms';
import { ResourceDetails } from '../../../Details/models';
import { listTimelineEvents } from '../../../Details/tabs/Timeline/api';
import { listTimelineEventsDecoder } from '../../../Details/tabs/Timeline/api/decoders';
import { TimelineEvent } from '../../../Details/tabs/Timeline/models';
import { Resource } from '../../../models';
import MemoizedGraphActions from '../GraphActions';
import { resourceDetailsUpdatedAtom } from '../TimePeriods/timePeriodAtoms';
import { FilterLines, GraphOptionId, LinesProps, NewLines } from '../models';
import { useIntersection } from '../useGraphIntersection';

import { graphOptionsAtom } from './graphOptionsAtoms';

const useStyles = makeStyles()((theme: Theme) => ({
  graph: {
    height: '100%',
    margin: 'auto',
    width: '100%'
  },
  graphContainer: {
    display: 'grid',
    gridTemplateRows: '1fr',
    padding: theme.spacing(2, 1, 1)
  }
}));

interface Parameters {
  end;
  start;
  timelineEventsLimit;
}

interface Props {
  filterLines?: ({ lines, resource }: FilterLines) => NewLines;
  graphHeight: number;
  graphTimeParameters: Parameters;
  interactWithGraph: boolean;
  limitLegendRows?: boolean;
  renderAdditionalLines?: (args: LinesProps) => ReactNode;
  resource?: Resource | ResourceDetails;
}

const ExportablePerformanceGraphWithTimeline = <T,>({
  resource,
  graphHeight,
  limitLegendRows,
  interactWithGraph,
  renderAdditionalLines,
  filterLines,
  graphTimeParameters
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const [timeline, setTimeline] = useState<Array<TimelineEvent>>();
  const [performanceGraphRef, setPerformanceGraphRef] =
    useState<HTMLDivElement | null>(null);

  const { sendRequest: sendGetTimelineRequest } = useRequest<
    ListingModel<TimelineEvent>
  >({
    decoder: listTimelineEventsDecoder,
    request: listTimelineEvents
  });

  const { alias } = useAtomValue(userAtom);
  const graphOptions = useAtomValue(graphOptionsAtom);
  const resourceDetailsUpdated = useAtomValue(resourceDetailsUpdatedAtom);
  const details = useAtomValue(detailsAtom);

  const { end, start, timelineEventsLimit } = graphTimeParameters || {};

  const graphContainerRef = useRef<HTMLElement | null>(null);

  const { setElement, isInViewport } = useIntersection();
  const xAxisTickFormat = getXAxisTickFormat({ end, start });

  const displayEventAnnotations = path<boolean>(
    [GraphOptionId.displayEvents, 'value'],
    graphOptions
  );

  const endpoint = path(['links', 'endpoints', 'performance_graph'], resource);
  const timelineEndpoint = path<string>(
    ['links', 'endpoints', 'timeline'],
    resource
  );

  const retrieveTimeline = (): void => {
    if (or(isNil(timelineEndpoint), not(displayEventAnnotations))) {
      setTimeline([]);

      return;
    }

    sendGetTimelineRequest({
      endpoint: timelineEndpoint,
      parameters: {
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
      }
    }).then(({ result }) => {
      setTimeline(result);
    });
  };

  useEffect(() => {
    if (isNil(endpoint)) {
      return;
    }

    retrieveTimeline();
  }, [endpoint, displayEventAnnotations, end, start]);

  useEffect(() => {
    setElement(graphContainerRef.current);
  }, []);

  const graphEndpoint = useMemo((): string | undefined => {
    if (isNil(endpoint)) {
      return undefined;
    }

    return `${endpoint}?start=${start}&end=${end}`;
  }, [details, end, start]);

  const addCommentToTimeline = ({ date, comment }): void => {
    const [id] = crypto.getRandomValues(new Uint16Array(1));

    setTimeline([
      ...(timeline as Array<TimelineEvent>),
      {
        contact: { name: alias },
        content: comment,
        date,
        id,
        type: 'comment'
      }
    ]);
  };

  const getPerformanceGraphRef = (ref): void => {
    setPerformanceGraphRef(ref);
  };

  return (
    <Paper className={classes.graphContainer}>
      <div
        className={classes.graph}
        ref={graphContainerRef as MutableRefObject<HTMLDivElement>}
      >
        <PerformanceGraph<T>
          canAdjustTimePeriod
          toggableLegend
          displayEventAnnotations={displayEventAnnotations}
          end={end}
          endpoint={graphEndpoint}
          filterLines={filterLines}
          getPerformanceGraphRef={getPerformanceGraphRef}
          graphActions={
            <MemoizedGraphActions
              end={end}
              open={interactWithGraph}
              performanceGraphRef={
                performanceGraphRef as unknown as MutableRefObject<HTMLDivElement | null>
              }
              resource={resource}
              start={start}
              timeline={timeline}
            />
          }
          graphHeight={graphHeight}
          interactWithGraph={interactWithGraph}
          isInViewport={isInViewport}
          limitLegendRows={limitLegendRows}
          renderAdditionalLines={renderAdditionalLines}
          resource={resource as Resource}
          resourceDetailsUpdated={resourceDetailsUpdated}
          start={start}
          timeline={timeline}
          xAxisTickFormat={xAxisTickFormat}
          onAddComment={addCommentToTimeline}
        />
      </div>
    </Paper>
  );
};

export default ExportablePerformanceGraphWithTimeline;
