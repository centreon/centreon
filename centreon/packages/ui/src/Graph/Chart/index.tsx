import {
  type MutableRefObject,
  RefCallback,
  memo,
  useEffect,
  useRef
} from 'react';

import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';
import 'dayjs/locale/pt';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';

import { ParentSize } from '../..';
import Loading from '../../LoadingSkeleton';
import type { LineChartData, Thresholds } from '../common/models';

import Chart from './Chart';
import { useChartStyles } from './Chart.styles';
import LoadingSkeleton from './LoadingSkeleton';
import type { GlobalAreaLines, LineChartProps } from './models';
import useChartData from './useChartData';
import useResizeObserver from 'use-resize-observer';

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
  getRef?: (ref: RefCallback<Element>) => void;
  containerStyle?: string;
  transformMatrix?: {
    fx?: (pointX: number) => number;
    fy?: (pointY: number) => number;
  };
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
  limitLegend,
  getRef,
  transformMatrix,
  additionalLines,
  ...rest
}: Props): JSX.Element | null => {
  const { classes, cx } = useChartStyles();

  const { adjustedData } = useChartData({ data, end, start });
  const {
    ref,
    width: responsiveWidth,
    height: responsiveHeight
  } = useResizeObserver();

  useEffect(() => {
    getRef?.(ref);
  }, [ref?.current]);

  if (loading && !adjustedData) {
    return (
      <LoadingSkeleton
        displayTitleSkeleton={header?.displayTitle ?? false}
        graphHeight={height || 200}
      />
    );
  }

  if (!adjustedData) {
    return <Loading height={height || 0} width={width} />;
  }

  return (
    <div
      ref={ref}
      className={cx(classes.wrapperContainer, rest?.containerStyle)}
    >
      <Chart
        annotationEvent={annotationEvent}
        axis={axis}
        barStyle={barStyle}
        displayAnchor={displayAnchor}
        graphData={adjustedData}
        graphInterval={{ end, start }}
        graphRef={ref}
        header={header}
        height={height || responsiveHeight || 0}
        legend={legend}
        limitLegend={limitLegend}
        lineStyle={lineStyle}
        shapeLines={shapeLines}
        thresholdUnit={thresholdUnit}
        thresholds={thresholds}
        timeShiftZones={timeShiftZones}
        tooltip={tooltip}
        width={width || responsiveWidth || 0}
        zoomPreview={zoomPreview}
        skipIntersectionObserver={rest.skipIntersectionObserver}
        additionalLines={additionalLines}
        transformMatrix={transformMatrix}
      />
    </div>
  );
};

export default memo(WrapperChart);
