import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';

import { Box } from '@mui/material';

import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { Provider } from 'jotai';
import type { ReactElement } from 'react';
import useResizeObserver from 'use-resize-observer';

import Loading from '../../LoadingSkeleton';
import LoadingSkeleton from '../Chart/LoadingSkeleton';
import type { LineChartProps } from '../Chart/models';
import useChartData from '../Chart/useChartData';
import type { LineChartData, Thresholds } from '../common/models';
import type { BarStyle } from './models';
import ResponsiveBarChart from './ResponsiveBarChart';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

export interface BarChartProps
  extends Partial<
    Pick<
      LineChartProps,
      | 'tooltip'
      | 'legend'
      | 'height'
      | 'axis'
      | 'header'
      | 'min'
      | 'max'
      | 'boundariesUnit'
      | 'timeShiftZones'
      | 'zoomPreview'
      | 'annotationEvent'
    >
  > {
  barStyle?: BarStyle;
  data?: LineChartData;
  end: string;
  limitLegend?: false | number;
  loading: boolean;
  orientation: 'vertical' | 'horizontal' | 'auto';
  start: string;
  thresholdUnit?: string;
  thresholds?: Thresholds;
  skipIntersectionObserver?: boolean;
}

const BarChart = ({
  data,
  end,
  start,
  height = 500,
  tooltip,
  axis,
  legend = {
    display: true,
    mode: 'grid',
    placement: 'bottom',
    showCalculations: {
      min: true,
      max: true,
      avg: true
    }
  },
  loading,
  limitLegend,
  thresholdUnit,
  thresholds,
  orientation = 'horizontal',
  header,
  barStyle = {
    opacity: 1,
    radius: 0.2
  },
  skipIntersectionObserver,
  min,
  max,
  boundariesUnit,
  zoomPreview,
  timeShiftZones,
  annotationEvent
}: BarChartProps): ReactElement => {
  const { adjustedData } = useChartData({ data, end, start, min, max });
  const { ref, width, height: responsiveHeight } = useResizeObserver();

  if (loading && !adjustedData) {
    return (
      <LoadingSkeleton
        displayTitleSkeleton={header?.displayTitle ?? false}
        graphHeight={height || 200}
      />
    );
  }

  if (!adjustedData) {
    return <div />;
  }

  return (
    <Provider>
      <Box ref={ref} sx={{ height: '100%', overflow: 'hidden', width: '100%' }}>
        {!responsiveHeight ? (
          <Loading height={height || '100%'} width={width} />
        ) : (
          <ResponsiveBarChart
            axis={axis}
            barStyle={barStyle}
            graphData={adjustedData}
            graphRef={ref}
            header={header}
            height={height || responsiveHeight || 0}
            legend={legend}
            limitLegend={limitLegend}
            orientation={orientation}
            thresholdUnit={thresholdUnit}
            thresholds={thresholds}
            tooltip={tooltip}
            width={width || 0}
            skipIntersectionObserver={skipIntersectionObserver}
            min={min}
            max={max}
            boundariesUnit={boundariesUnit}
            zoomPreview={zoomPreview}
            timeShiftZones={timeShiftZones}
            annotationEvent={annotationEvent}
            start={start}
            end={end}
          />
        )}
      </Box>
    </Provider>
  );
};

export default BarChart;
