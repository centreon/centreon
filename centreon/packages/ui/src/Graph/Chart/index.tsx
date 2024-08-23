import { MutableRefObject, memo, useRef } from 'react';

import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { equals } from 'ramda';

import { ParentSize } from '../..';
import Loading from '../../LoadingSkeleton';
import { LineChartData, Thresholds } from '../common/models';

import Chart from './Chart';
import LoadingSkeleton from './LoadingSkeleton';
import { GlobalAreaLines, LineChartProps } from './models';
import useChartData from './useChartData';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends Partial<LineChartProps> {
  data?: LineChartData;
  end: string;
  limitLegend?: false | number;
  loading: boolean;
  shapeLines?: GlobalAreaLines;
  start: string;
  thresholdUnit?: string;
  thresholds?: Thresholds;
}

const WrapperChart = ({
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
  tooltip = {
    mode: 'all',
    sortOrder: 'name'
  },
  annotationEvent,
  legend = {
    display: true,
    mode: 'grid',
    placement: 'bottom'
  },
  header,
  lineStyle,
  barStyle,
  thresholds,
  thresholdUnit,
  limitLegend
}: Props): JSX.Element | null => {
  const { adjustedData } = useChartData({ data, end, start });
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
    return <Loading height={height} width={width} />;
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
            <Chart
              annotationEvent={annotationEvent}
              axis={axis}
              barStyle={barStyle}
              displayAnchor={displayAnchor}
              graphData={adjustedData}
              graphInterval={{ end, start }}
              graphRef={lineChartRef}
              header={header}
              height={height || responsiveHeight}
              legend={legend}
              limitLegend={limitLegend}
              lineStyle={lineStyle}
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

export default memo(WrapperChart, equals);
