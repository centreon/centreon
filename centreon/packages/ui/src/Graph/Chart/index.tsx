import dayjs from "dayjs";
import { memo, useRef } from "react";
import "dayjs/locale/en";
import "dayjs/locale/es";
import "dayjs/locale/fr";
import "dayjs/locale/pt";
import localizedFormat from "dayjs/plugin/localizedFormat";
import timezonePlugin from "dayjs/plugin/timezone";
import utcPlugin from "dayjs/plugin/utc";
import useResizeObserver from "use-resize-observer";
import Loading from "../../LoadingSkeleton";
import type { LineChartData, Thresholds } from "../common/models";
import Chart from "./Chart";
import { useChartStyles } from "./Chart.styles";
import LoadingSkeleton from "./LoadingSkeleton";
import type { GlobalAreaLines, LineChartProps } from "./models";
import useChartData from "./useChartData";

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
  getRef?: (ref: React.RefObject<HTMLDivElement | null>) => void;
  containerStyle?: string;
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
    mode: "all",
    sortOrder: "name",
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
  ...rest
}: Props): JSX.Element | null => {
  const { classes, cx } = useChartStyles();
  const ref = useRef<HTMLDivElement | null>(null);

  const { adjustedData } = useChartData({ data, end, start });

  const containerRef = useRef<HTMLDivElement | null>(null);

  const {
    ref: resizeObserverRef,
    width: responsiveWidth,
    height: responsiveHeight,
  } = useResizeObserver();

  const combinedRef = (element: HTMLDivElement | null) => {
    if (containerRef.current !== element) {
      containerRef.current = element;
      if (element) {
        getRef?.(containerRef);
      }
    }
    resizeObserverRef(element);
  };


  if (loading && !adjustedData) {
    return (
      <LoadingSkeleton
        displayTitleSkeleton={header?.displayTitle ?? false}
        graphHeight={height || 200}
      />
    );
  }

  return (
    <div
      ref={combinedRef}
      className={cx(classes.wrapperContainer, rest?.containerStyle)}
    >
      {!responsiveHeight || !data ? (
        <Loading height={height || '100%'} width={width} />
      ) : (
        <Chart
          annotationEvent={annotationEvent}
          axis={axis}
          barStyle={barStyle}
          displayAnchor={displayAnchor}
          graphData={adjustedData}
          graphInterval={{ end, start }}
          graphRef={containerRef}
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
          width={width || responsiveWidth || 0}
          zoomPreview={zoomPreview}
        />
      )}
    </div>
  );
};

export default memo(WrapperChart);
