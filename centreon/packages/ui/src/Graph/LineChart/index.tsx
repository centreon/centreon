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

import { LineChartData } from '../common/models';

import LineChart from './LineChart';
import LoadingSkeleton from './LoadingSkeleton';
import { GlobalAreaLines, LineChartProps, LegendModel } from './models';
import useLineChartData from './useLineChartData';
import { CurveType } from './BasicComponents/Lines/models';

dayjs.extend(localizedFormat);
dayjs.extend(utcPlugin);
dayjs.extend(timezonePlugin);

interface Props extends Partial<LineChartProps> {
  curve?: CurveType;
  data?: LineChartData;
  disabledThresholds?: boolean;
  end: string;
  legend: LegendModel;
  loading: boolean;
  marginBottom?: number;
  shapeLines?: GlobalAreaLines;
  start: string;
  thresholdLabels?: Array<string>;
  thresholdUnit?: string;
  thresholds?: Array<number>;
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
  curve = Curve.curveLinear,
  marginBottom = 0,
  thresholds,
  thresholdUnit,
  thresholdLabels,
  disabledThresholds
}: Props): JSX.Element | null => {
  const { adjustedData } = useLineChartData({ data, end, start });
  const lineChartRef = useRef<HTMLDivElement | null>(null);

  if (loading && !adjustedData) {
    return (
      <LoadingSkeleton
        displayTitleSkeleton={header?.displayTitle ?? true}
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
      style={{ height: '100%', width: '100%' }}
    >
      <Responsive.ParentSize>
        {({
          height: responsiveHeight,
          width: responsiveWidth
        }): JSX.Element => {
          return (
            <LineChart
              annotationEvent={annotationEvent}
              axis={axis}
              curve={curve}
              disabledThresholds={disabledThresholds}
              displayAnchor={displayAnchor}
              graphData={adjustedData}
              graphInterval={{ end, start }}
              graphRef={lineChartRef}
              header={header}
              height={height || responsiveHeight}
              legend={legend}
              loading={loading}
              marginBottom={marginBottom}
              shapeLines={shapeLines}
              thresholdLabels={thresholdLabels}
              thresholdUnit={thresholdUnit}
              thresholds={thresholds}
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

export default WrapperLineChart;
