import { MutableRefObject, useRef } from 'react';

import { Responsive } from '@visx/visx';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import Graph from './Graph';
import LoadingSkeleton from './LoadingSkeleton';
import { GlobalAreaLines, GraphData, GraphProps, LegendModel } from './models';
import useGraphData from './useGraphData';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends Partial<GraphProps> {
  data: GraphData;
  end: string;
  legend: LegendModel;
  loading: boolean;
  shapeLines?: GlobalAreaLines;
  start: string;
}

const WrapperGraph = ({
  end,
  start,
  height = 500,
  width,
  shapeLines,
  axis,
  anchorPoint,
  zoomPreview,
  data,
  loading,
  timeShiftZones,
  tooltip,
  annotationEvent,
  legend
}: Props): JSX.Element | null => {
  const { adjustedData } = useGraphData({ data, end, start });
  const graphRef = useRef<HTMLDivElement | null>(null);

  if (loading || !adjustedData) {
    return <LoadingSkeleton displayTitleSkeleton graphHeight={height} />;
  }

  return (
    <div ref={graphRef as MutableRefObject<HTMLDivElement>}>
      <Responsive.ParentSize>
        {({
          height: responsiveHeight,
          width: responsiveWidth
        }): JSX.Element => (
          <Graph
            anchorPoint={anchorPoint}
            annotationEvent={annotationEvent}
            axis={axis}
            graphData={{ ...adjustedData }}
            graphInterval={{ end, start }}
            graphRef={graphRef}
            height={height ?? responsiveHeight}
            legend={legend}
            loading={loading}
            shapeLines={shapeLines}
            timeShiftZones={timeShiftZones}
            tooltip={tooltip}
            width={width ?? responsiveWidth}
            zoomPreview={zoomPreview}
          />
        )}
      </Responsive.ParentSize>
    </div>
  );
};

export default WrapperGraph;
