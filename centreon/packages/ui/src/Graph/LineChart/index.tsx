import { MutableRefObject, useRef } from 'react';

import { Curve } from '@visx/visx';
import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import { ParentSize } from '../..';
import { LineChartData, Thresholds } from '../common/models';

import LineChart from './LineChart';
import LoadingSkeleton from './LoadingSkeleton';
import { GlobalAreaLines, LineChartProps, LegendModel } from './models';
import useLineChartData from './useLineChartData';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends Partial<LineChartProps> {
  curve?: 'linear' | 'step' | 'natural';
  data?: LineChartData;
  end: string;
  legend: LegendModel;
  limitLegend?: false | number;
  loading: boolean;
  shapeLines?: GlobalAreaLines;
  start: string;
  thresholdUnit?: string;
  thresholds?: Thresholds;
}

const WrapperLineChart = ({
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
  curve = 'linear',
  thresholds,
  thresholdUnit,
  limitLegend
}: Props): JSX.Element | null => {
  const { adjustedData } = useLineChartData({ data, end, start });
  const lineChartRef = useRef<HTMLDivElement | null>(null);

  if (loading && !adjustedData) {
    return (
      <LoadingSkeleton
        displayTitleSkeleton={header?.displayTitle ?? false}
        graphHeight={height || 200}
      />
    );
  }

  if (!adjustedData) {
    return null;
  }

  return (
    <div
      ref={lineChartRef as MutableRefObject<HTMLDivElement>}
      style={{ height: '100%', overflow: 'hidden', width: '100%' }}
    >
      <ParentSize>
        {({
          height: responsiveHeight,
          width: responsiveWidth
        }): JSX.Element => {
          return (
            <LineChart
              annotationEvent={annotationEvent}
              axis={axis}
              curve={curve}
              displayAnchor={displayAnchor}
              graphData={adjustedData}
              graphInterval={{ end, start }}
              graphRef={lineChartRef}
              header={header}
              height={height || responsiveHeight}
              legend={legend}
              limitLegend={limitLegend}
              shapeLines={shapeLines}
              thresholdUnit={thresholdUnit}
              thresholds={thresholds}
              timeShiftZones={timeShiftZones}
              tooltip={tooltip}
              width={width ?? responsiveWidth}
              zoomPreview={zoomPreview}
            />
          );
        }}
      </ParentSize>
    </div>
  );
};

export default WrapperLineChart;
