import {
  type Interval,
  LineChart,
  type LineChartData,
  type TooltipData,
  useFetchQuery
} from '@centreon/ui';
import { path } from 'ramda';
import { useRef, useState } from 'react';
import useRetrieveTimeLine from '../../../Graph/Performance/ExportableGraphWithTimeline/useRetrieveTimeLine';
import MemoizedGraphActions from '../../../Graph/Performance/GraphActions';
import { useChartGraphStyles } from './chartGraph.styles';

const ChartGraph = ({ graphInterval, resource, updatedGraphInterval }) => {
  const { classes } = useChartGraphStyles();
  const [graphRef, setGraphRef] = useState();

  const ref = useRef();
  const timelineEndpoint = path<string>(
    ['links', 'endpoints', 'timeline'],
    resource
  );

  const timeLineData = useRetrieveTimeLine({
    timelineEndpoint,
    start: graphInterval?.start,
    end: graphInterval?.end,
    timelineEventsLimit: graphInterval?.timelineEventsLimit
  });

  const graphEndpoint = path<string>(
    ['links', 'endpoints', 'performance_graph'],
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

  const getInterval = (interval: Interval): void => {
    updatedGraphInterval(interval);
  };

  const extraComponent = graphInterval && (
    <MemoizedGraphActions
      end={graphInterval.end}
      performanceGraphRef={graphRef}
      resource={resource}
      start={graphInterval.start}
      timeline={[]}
    />
  );

  const getRef = (ref) => {
    setGraphRef(ref);
  };

  console.log({ timeLineData });

  return (
    <LineChart
      annotationEvent={{ data: timeLineData ? timeLineData.result : [] }}
      containerStyle={classes.container}
      getRef={getRef}
      ref={ref}
      data={data}
      end={graphInterval?.end}
      height={280}
      legend={{ mode: 'grid', placement: 'bottom' }}
      lineStyle={{ lineWidth: 1 }}
      header={{ extraComponent }}
      tooltip={{
        // mode: "all",
        // sortOrder: "name",
        renderComponent: ({
          data,
          tooltipOpen,
          hideTooltip
        }: TooltipData): JSX.Element => {
          return <div> hola</div>;
        }
        // <ExternalComponent
        // 	data={data}
        // 	hideTooltip={hideTooltip}
        // 	openTooltip={tooltipOpen}
        // />
      }}
      // tooltip={{ enable: false }}
      // shapeLines={{
      //     areaThresholdLines: getShapeLines,
      // }}
      start={graphInterval?.start}
      timeShiftZones={{ enable: true, getInterval }}
      zoomPreview={{ enable: true, getInterval }}
    />
  );
};

export default ChartGraph;
