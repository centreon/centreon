import {
  type Interval,
  LineChart,
  type LineChartData,
  type Parameters,
  type TooltipData,
  useFetchQuery
} from '@centreon/ui';
import { path } from 'ramda';
import { type MutableRefObject, useState } from 'react';
import FederatedComponent from '../../../../components/FederatedComponents';
import MemoizedGraphActions from '../../../Graph/Performance/GraphActions';
import type { Resource } from '../../../models';
import type { ResourceDetails } from '../../models';
import Comment from './Comment';
import { useChartGraphStyles } from './chartGraph.styles';
import useRetrieveTimeLine from './useRetrieveTimeLine';

interface Props {
  graphTimeParameters?: Parameters;
  resource?: ResourceDetails | Resource;
  updatedGraphInterval: (args: Interval) => void;
}

const ChartGraph = ({
  graphTimeParameters,
  resource,
  updatedGraphInterval
}: Props) => {
  const { classes } = useChartGraphStyles();

  const [graphRef, setGraphRef] = useState<MutableRefObject<HTMLDivElement>>();
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
      `${graphEndpoint}?start=${graphTimeParameters?.start}&end=${graphTimeParameters?.end}`,
    getQueryKey: () => [
      'graphPerformance',
      graphTimeParameters?.start,
      graphTimeParameters?.end,
      graphEndpoint
    ],
    queryOptions: {
      enabled: !!graphTimeParameters && !!graphEndpoint,
      suspense: false
    }
  });

  const timeLineData = useRetrieveTimeLine({
    timelineEndpoint,
    graphTimeParameters
  });

  const getInterval = (interval: Interval): void => {
    updatedGraphInterval(interval);
  };

  const getRef = (ref: MutableRefObject<HTMLDivElement>) => {
    setGraphRef(ref);
  };

  const graphActions = graphTimeParameters && (
    <MemoizedGraphActions
      end={graphTimeParameters.end}
      performanceGraphRef={graphRef}
      resource={resource}
      start={graphTimeParameters.start}
      timeline={[]}
    />
  );

  const getShapeLines = (callback) => {
    setAreaThresholdLines(callback(resource?.uuid));
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
        end={graphTimeParameters?.end}
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
        start={graphTimeParameters?.start}
        timeShiftZones={{ enable: true, getInterval }}
        zoomPreview={{ enable: true, getInterval }}
        {...rest}
      />
    </>
  );
};

export default ChartGraph;
