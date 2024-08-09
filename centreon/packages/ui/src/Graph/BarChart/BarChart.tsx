import { useRef } from 'react';

import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { Provider } from 'jotai';

import { Box } from '@mui/material';

import { LineChartProps } from '../Chart/models';
import { LineChartData, Thresholds } from '../common/models';
import { ParentSize } from '../../ParentSize';
import useChartData from '../Chart/useChartData';
import LoadingSkeleton from '../Chart/LoadingSkeleton';

import ResponsiveBarChart from './ResponsiveBarChart';
import { BarStyle } from './models';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

export interface BarChartProps
  extends Partial<
    Pick<LineChartProps, 'tooltip' | 'legend' | 'height' | 'axis' | 'header'>
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
}

const BarChart = ({
  data,
  end,
  start,
  height = 500,
  tooltip,
  axis,
  legend,
  loading,
  limitLegend,
  thresholdUnit,
  thresholds,
  orientation = 'horizontal',
  header,
  barStyle = {
    opacity: 1,
    radius: 0.2
  }
}: BarChartProps): JSX.Element => {
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

  return (
    <Provider>
      <Box
        ref={lineChartRef}
        sx={{ height: '100%', overflow: 'hidden', width: '100%' }}
      >
        <ParentSize>
          {({ height: responsiveHeight, width }) => (
            <ResponsiveBarChart
              axis={axis}
              barStyle={barStyle}
              graphData={adjustedData}
              graphRef={lineChartRef}
              header={header}
              height={height || responsiveHeight}
              legend={legend}
              limitLegend={limitLegend}
              orientation={orientation}
              thresholdUnit={thresholdUnit}
              thresholds={thresholds}
              tooltip={tooltip}
              width={width}
            />
          )}
        </ParentSize>
      </Box>
    </Provider>
  );
};

export default BarChart;
