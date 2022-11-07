<<<<<<< HEAD
import { MutableRefObject, useEffect, useMemo, useRef, useState } from 'react';

import { path, isNil, or, not } from 'ramda';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import { Paper, Theme } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import { useRequest, ListingModel } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';
=======
import * as React from 'react';

import { path, isNil, or, not } from 'ramda';

import { Paper, Theme, makeStyles } from '@material-ui/core';

import { useRequest, ListingModel } from '@centreon/ui';
import { useUserContext } from '@centreon/ui-context';
>>>>>>> centreon/dev-21.10.x

import { TimelineEvent } from '../../../Details/tabs/Timeline/models';
import { listTimelineEvents } from '../../../Details/tabs/Timeline/api';
import { listTimelineEventsDecoder } from '../../../Details/tabs/Timeline/api/decoders';
import PerformanceGraph from '..';
import { Resource } from '../../../models';
import { ResourceDetails } from '../../../Details/models';
import { GraphOptionId } from '../models';
import { useIntersection } from '../useGraphIntersection';
<<<<<<< HEAD
import {
  adjustTimePeriodDerivedAtom,
  customTimePeriodAtom,
  getDatesDerivedAtom,
  graphQueryParametersDerivedAtom,
  resourceDetailsUpdatedAtom,
  selectedTimePeriodAtom,
} from '../TimePeriods/timePeriodAtoms';
import { detailsAtom } from '../../../Details/detailsAtoms';

import { graphOptionsAtom } from './graphOptionsAtoms';
=======
import { useResourceContext } from '../../../Context';
import { ResourceGraphMousePosition } from '../../../Details/tabs/Services/Graphs';

import { defaultGraphOptions, useGraphOptionsContext } from './useGraphOptions';
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme: Theme) => ({
  graph: {
    height: '100%',
    margin: 'auto',
    width: '100%',
  },
  graphContainer: {
    display: 'grid',
    gridTemplateRows: '1fr',
<<<<<<< HEAD
    height: '93%',
=======
>>>>>>> centreon/dev-21.10.x
    padding: theme.spacing(2, 1, 1),
  },
}));

interface Props {
  graphHeight: number;
  limitLegendRows?: boolean;
  resource?: Resource | ResourceDetails;
<<<<<<< HEAD
=======
  resourceGraphMousePosition?: ResourceGraphMousePosition | null;
  updateResourceGraphMousePosition?: (
    resourceGraphMousePosition: ResourceGraphMousePosition | null,
  ) => void;
>>>>>>> centreon/dev-21.10.x
}

const ExportablePerformanceGraphWithTimeline = ({
  resource,
  graphHeight,
  limitLegendRows,
<<<<<<< HEAD
}: Props): JSX.Element => {
  const classes = useStyles();

  const [timeline, setTimeline] = useState<Array<TimelineEvent>>();
=======
  updateResourceGraphMousePosition,
  resourceGraphMousePosition,
}: Props): JSX.Element => {
  const classes = useStyles();

  const {
    customTimePeriod,
    getIntervalDates,
    periodQueryParameters,
    adjustTimePeriod,
    selectedTimePeriod,
    resourceDetailsUpdated,
  } = useResourceContext();

  const [timeline, setTimeline] = React.useState<Array<TimelineEvent>>();
>>>>>>> centreon/dev-21.10.x
  const { sendRequest: sendGetTimelineRequest } = useRequest<
    ListingModel<TimelineEvent>
  >({
    decoder: listTimelineEventsDecoder,
    request: listTimelineEvents,
  });

<<<<<<< HEAD
  const { alias } = useAtomValue(userAtom);
  const graphOptions = useAtomValue(graphOptionsAtom);
  const getGraphQueryParameters = useAtomValue(graphQueryParametersDerivedAtom);
  const selectedTimePeriod = useAtomValue(selectedTimePeriodAtom);
  const customTimePeriod = useAtomValue(customTimePeriodAtom);
  const resourceDetailsUpdated = useAtomValue(resourceDetailsUpdatedAtom);
  const getIntervalDates = useAtomValue(getDatesDerivedAtom);
  const details = useAtomValue(detailsAtom);
  const adjustTimePeriod = useUpdateAtom(adjustTimePeriodDerivedAtom);

  const graphContainerRef = useRef<HTMLElement | null>(null);
=======
  const { alias } = useUserContext();

  const graphOptions =
    useGraphOptionsContext()?.graphOptions || defaultGraphOptions;
  const graphContainerRef = React.useRef<HTMLElement | null>(null);
>>>>>>> centreon/dev-21.10.x

  const { setElement, isInViewport } = useIntersection();

  const displayEventAnnotations = path<boolean>(
    [GraphOptionId.displayEvents, 'value'],
    graphOptions,
  );

  const endpoint = path(['links', 'endpoints', 'performance_graph'], resource);
  const timelineEndpoint = path<string>(
    ['links', 'endpoints', 'timeline'],
    resource,
  );

  const retrieveTimeline = (): void => {
    if (or(isNil(timelineEndpoint), not(displayEventAnnotations))) {
      setTimeline([]);

      return;
    }

<<<<<<< HEAD
    const [start, end] = getIntervalDates(selectedTimePeriod);
=======
    const [start, end] = getIntervalDates();
>>>>>>> centreon/dev-21.10.x

    sendGetTimelineRequest({
      endpoint: timelineEndpoint,
      parameters: {
        limit:
          selectedTimePeriod?.timelineEventsLimit ||
          customTimePeriod.timelineLimit,
        search: {
          conditions: [
            {
              field: 'date',
              values: {
                $gt: start,
                $lt: end,
              },
            },
          ],
        },
      },
    }).then(({ result }) => {
      setTimeline(result);
    });
  };

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(endpoint)) {
      return;
    }

    retrieveTimeline();
  }, [endpoint, selectedTimePeriod, customTimePeriod, displayEventAnnotations]);

<<<<<<< HEAD
  useEffect(() => {
    setElement(graphContainerRef.current);
  }, []);

  const graphEndpoint = useMemo((): string | undefined => {
=======
  React.useEffect(() => {
    setElement(graphContainerRef.current);
  }, []);

  const getEndpoint = (): string | undefined => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(endpoint)) {
      return undefined;
    }

<<<<<<< HEAD
    const graphQuerParameters = getGraphQueryParameters({
      endDate: customTimePeriod.end,
      startDate: customTimePeriod.start,
      timePeriod: selectedTimePeriod,
    });

    return `${endpoint}${graphQuerParameters}`;
  }, [
    customTimePeriod.start.toISOString(),
    customTimePeriod.end.toISOString(),
    details,
  ]);
=======
    return `${endpoint}${periodQueryParameters}`;
  };
>>>>>>> centreon/dev-21.10.x

  const addCommentToTimeline = ({ date, comment }): void => {
    const [id] = crypto.getRandomValues(new Uint16Array(1));

    setTimeline([
      ...(timeline as Array<TimelineEvent>),
      {
        contact: { name: alias },
        content: comment,
        date,
        id,
        type: 'comment',
      },
    ]);
  };

  return (
    <Paper className={classes.graphContainer}>
      <div
        className={classes.graph}
<<<<<<< HEAD
        ref={graphContainerRef as MutableRefObject<HTMLDivElement>}
=======
        ref={graphContainerRef as React.MutableRefObject<HTMLDivElement>}
>>>>>>> centreon/dev-21.10.x
      >
        <PerformanceGraph
          toggableLegend
          adjustTimePeriod={adjustTimePeriod}
          customTimePeriod={customTimePeriod}
          displayEventAnnotations={displayEventAnnotations}
<<<<<<< HEAD
          endpoint={graphEndpoint}
=======
          endpoint={getEndpoint()}
>>>>>>> centreon/dev-21.10.x
          graphHeight={graphHeight}
          isInViewport={isInViewport}
          limitLegendRows={limitLegendRows}
          resource={resource as Resource}
          resourceDetailsUpdated={resourceDetailsUpdated}
<<<<<<< HEAD
          timeline={timeline}
=======
          resourceGraphMousePosition={resourceGraphMousePosition}
          timeline={timeline}
          updateResourceGraphMousePosition={updateResourceGraphMousePosition}
>>>>>>> centreon/dev-21.10.x
          xAxisTickFormat={
            selectedTimePeriod?.dateTimeFormat ||
            customTimePeriod.xAxisTickFormat
          }
          onAddComment={addCommentToTimeline}
        />
      </div>
    </Paper>
  );
};

export default ExportablePerformanceGraphWithTimeline;
