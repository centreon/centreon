import {
  type Interval,
  LineChart,
  type LineChartData,
  type TooltipData,
  useFetchQuery
} from '@centreon/ui';
import { path } from 'ramda';
import { useState } from 'react';
import FederatedComponent from '../../../../components/FederatedComponents';
import MemoizedGraphActions from '../../../Graph/Performance/GraphActions';
import Comment from './Comment';
import { useChartGraphStyles } from './chartGraph.styles';
import useRetrieveTimeLine from './useRetrieveTimeLine';

const ChartGraph = ({ graphInterval, resource, updatedGraphInterval }) => {
  const { classes } = useChartGraphStyles();

  const [graphRef, setGraphRef] = useState();

  const [areaThresholdLines, setAreaThresholdLines] = useState();

  const graphEndpoint = path<string>(
    ['links', 'endpoints', 'performance_graph'],
    resource
  );

  const timelineEndpoint = path<string>(
    ['links', 'endpoints', 'timeline'],
    resource
  );

  const { data } = useFetchQuery<LineChartData>({
    getEndpoint: () =>
      `${graphEndpoint}?start=${graphInterval?.start}&end=${graphInterval?.end}`,
    getQueryKey: () => [
      'graphPerformance',
      graphInterval?.start,
      graphInterval?.end,
      graphEndpoint
    ],
    queryOptions: {
      enabled: !!graphInterval && !!graphEndpoint,
      suspense: false
    }
  });

  const timeLineData = useRetrieveTimeLine({
    timelineEndpoint,
    start: graphInterval?.start,
    end: graphInterval?.end,
    timelineEventsLimit: graphInterval?.timelineEventsLimit
  });

  const getInterval = (interval: Interval): void => {
    updatedGraphInterval(interval);
  };

  const getRef = (ref) => {
    setGraphRef(ref);
  };

  const graphActions = graphInterval && (
    <MemoizedGraphActions
      end={graphInterval.end}
      performanceGraphRef={graphRef}
      resource={resource}
      start={graphInterval.start}
      timeline={[]}
    />
  );

  const getShapeLines = (callback) => {
    setAreaThresholdLines(callback(resource.uuid));
  };

  const rest = areaThresholdLines ? { shapeLines: areaThresholdLines } : {};

  return (
    <>
      <FederatedComponent
        path="/anomaly-detection/enableThresholdLines"
        styleMenuSkeleton={{ height: 0, width: 0 }}
        type={resource?.type}
        getShapeLines={getShapeLines}
      />
      <LineChart
        annotationEvent={{ data: timeLineData }}
        containerStyle={classes.container}
        getRef={getRef}
        data={data}
        end={graphInterval?.end}
        height={280}
        legend={{ mode: 'grid', placement: 'bottom' }}
        lineStyle={{ lineWidth: 1 }}
        header={{ extraComponent: graphActions }}
        tooltip={{
          renderComponent: ({
            data,
            hideTooltip
          }: TooltipData): JSX.Element => (
            <Comment
              commentDate={data}
              hideAddCommentTooltip={hideTooltip}
              resource={resource}
            />
          )
        }}
        start={graphInterval?.start}
        timeShiftZones={{ enable: true, getInterval }}
        zoomPreview={{ enable: true, getInterval }}
        {...rest}
      />
    </>
  );
};

export default ChartGraph;
