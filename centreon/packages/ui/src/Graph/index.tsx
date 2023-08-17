import { MutableRefObject, useRef } from 'react';

import { Curve, Responsive } from '@visx/visx';
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
import { CurveType } from './BasicComponents/Lines/models';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends Partial<GraphProps> {
  curve?: CurveType;
  data?: GraphData;
  end: string;
  legend: LegendModel;
  loading: boolean;
  marginBottom?: number;
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
  displayAnchor,
  zoomPreview,
  data,
  loading,
  timeShiftZones,
  tooltip,
  annotationEvent,
  legend,
  header,
  curve = Curve.curveLinear,
  marginBottom = 0
}: Props): JSX.Element | null => {
  const { adjustedData } = useGraphData({ data, end, start });
  const graphRef = useRef<HTMLDivElement | null>(null);

  if (loading || !adjustedData) {
    return (
      <LoadingSkeleton
        displayTitleSkeleton={header?.displayTitle ?? true}
        graphHeight={height || 200}
      />
    );
  }

  return (
    <div
      ref={graphRef as MutableRefObject<HTMLDivElement>}
      style={{ height: '100%', width: '100%' }}
    >
      <Responsive.ParentSize>
        {({
          height: responsiveHeight,
          width: responsiveWidth
        }): JSX.Element => {
          return (
            <Graph
              annotationEvent={annotationEvent}
              axis={axis}
              curve={curve}
              displayAnchor={displayAnchor}
              graphData={adjustedData}
              graphInterval={{ end, start }}
              graphRef={graphRef}
              header={header}
              height={height || responsiveHeight}
              legend={legend}
              loading={loading}
              marginBottom={marginBottom}
              shapeLines={shapeLines}
              timeShiftZones={timeShiftZones}
              tooltip={tooltip}
              width={width ?? responsiveWidth}
              zoomPreview={zoomPreview}
            />
          );
        }}
      </Responsive.ParentSize>
    </div>
  );
};

export default WrapperGraph;
