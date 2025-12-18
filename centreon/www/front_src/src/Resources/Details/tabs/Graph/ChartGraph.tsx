import {
  type Interval,
  LineChart,
  type LineChartData,
  type Parameters,
  type TooltipData,
  useFetchQuery
} from '@centreon/ui';

import { path } from 'ramda';
import { ReactElement, RefObject, useState } from 'react';

import FederatedComponent from '../../../../components/FederatedComponents';
import { graphsCapNumber } from '../../../constants';
import MemoizedGraphActions from '../../../Graph/Performance/GraphActions';
import type { Resource } from '../../../models';
import TooManyElementsCard from '../../../TooManyElementsCard';
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

  const [graphRef, setGraphRef] = useState<RefObject<HTMLDivElement>>();
  const [areaThresholdLines, setAreaThresholdLines] = useState();

  const graphEndpoint = path<string>(
    ['links', 'endpoints', 'performance_graph'],
    resource
  );

  const timelineEndpoint = path<string>(
    ['links', 'endpoints', 'timeline'],
    resource
  );

  const { data, isLoading, isFetching } = useFetchQuery<LineChartData>({
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
    graphTimeParameters,
    timelineEndpoint
  });

  const getInterval = (interval: Interval): void => {
    updatedGraphInterval(interval);
  };

  const getRef = (ref: RefObject<HTMLDivElement>) => {
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

  const metricsCount = data?.metrics?.length ?? 0;
  if (metricsCount > graphsCapNumber) {
    return (
      <TooManyElementsCard
        actions={graphActions}
        listing={false}
        title={data?.global.title}
      />
    );
  }

  return (
    <>
      <FederatedComponent
        getShapeLines={getShapeLines}
        path="/anomaly-detection/enableThresholdLines"
        styleMenuSkeleton={{ height: 0, width: 0 }}
        type={resource?.type}
      />
      <LineChart
        annotationEvent={{ data: timeLineData }}
        containerStyle={classes.container}
        data={data}
        end={graphTimeParameters?.end}
        getRef={getRef}
        header={{ extraComponent: graphActions }}
        height={280}
        legend={{ mode: 'grid', placement: 'bottom' }}
        lineStyle={{ lineWidth: 1 }}
        loading={isFetching || isLoading || !data}
        start={graphTimeParameters?.start}
        timeShiftZones={{ enable: true, getInterval }}
        tooltip={{
          renderComponent: ({
            data,
            hideTooltip
          }: TooltipData): ReactElement => (
            <Comment
              commentDate={data}
              hideAddCommentTooltip={hideTooltip}
              resource={resource}
            />
          )
        }}
        zoomPreview={{ enable: true, getInterval }}
        {...rest}
      />
    </>
  );
};

export default ChartGraph;
