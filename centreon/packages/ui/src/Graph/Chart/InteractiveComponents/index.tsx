import { MutableRefObject } from 'react';

import { Event } from '@visx/visx';
import { ScaleLinear, ScaleTime } from 'd3-scale';
import { useSetAtom } from 'jotai';
import {
  all,
  equals,
  find,
  isEmpty,
  isNil,
  keys,
  map,
  negate,
  pick,
  pipe,
  pluck,
  reduce,
  toPairs,
  values
} from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  formatMetricName,
  getLineForMetric,
  getLinesForMetrics,
  getTimeValue,
  getYScale
} from '../../common/timeSeries';
import { Line, TimeValue } from '../../common/timeSeries/models';
import { margin } from '../common';
import {
  AnnotationEvent,
  GraphInterval,
  InteractedZone,
  InteractedZone as ZoomPreviewModel
} from '../models';

import Annotations from './Annotations';
import { TimelineEvent } from './Annotations/models';
import Bar from './Bar';
import TimeShiftZones from './TimeShiftZones';
import ZoomPreview from './ZoomPreview';
import {
  MousePosition,
  changeMousePositionDerivedAtom,
  eventMouseDownAtom,
  eventMouseLeaveAtom,
  eventMouseUpAtom,
  graphTooltipDataAtom
} from './interactionWithGraphAtoms';

const useStyles = makeStyles()(() => ({
  overlay: {
    cursor: 'crosshair'
  }
}));

interface CommonData {
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  lines;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number>;
  yScalesPerUnit: Record<string, ScaleLinear<string, string>>;
}

interface TimeShiftZonesData extends InteractedZone {
  graphInterval: GraphInterval;
}

interface Props {
  annotationData?: AnnotationEvent;
  commonData: CommonData;
  timeShiftZonesData: TimeShiftZonesData;
  zoomData: ZoomPreviewModel;
}

const InteractionWithGraph = ({
  zoomData,
  commonData,
  annotationData,
  timeShiftZonesData
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const setEventMouseDown = useSetAtom(eventMouseDownAtom);
  const setEventMouseUp = useSetAtom(eventMouseUpAtom);
  const setEventMouseLeave = useSetAtom(eventMouseLeaveAtom);
  const changeMousePosition = useSetAtom(changeMousePositionDerivedAtom);
  const setGraphTooltipData = useSetAtom(graphTooltipDataAtom);

  const {
    graphHeight,
    graphWidth,
    graphSvgRef,
    xScale,
    timeSeries,
    lines,
    yScalesPerUnit
  } = commonData;

  const displayZoomPreview = zoomData?.enable ?? true;

  const displayEventAnnotations =
    !isNil(annotationData?.data) && !isEmpty(annotationData?.data);
  const displayTimeShiftZones = timeShiftZonesData?.enable ?? true;

  const mouseLeave = (event): void => {
    setEventMouseLeave(event);
    setEventMouseDown(null);
    updateMousePosition(null);
    setGraphTooltipData(null);
  };

  const mouseUp = (event): void => {
    setEventMouseUp(event);
    setEventMouseDown(null);
  };

  const mouseMove = (event): void => {
    const mousePoint = Event.localPoint(
      graphSvgRef?.current as SVGSVGElement,
      event
    );
    if (!mousePoint) {
      return;
    }
    updateMousePosition([mousePoint.x, mousePoint.y]);
  };

  const mouseDown = (event): void => {
    setEventMouseDown(event);
  };

  const updateMousePosition = (pointPosition: MousePosition): void => {
    if (isNil(pointPosition)) {
      changeMousePosition({
        position: null
      });
      setGraphTooltipData(null);

      return;
    }
    const timeValue = getTimeValue({
      timeSeries,
      x: pointPosition[0],
      xScale
    });

    if (isNil(timeValue)) {
      changeMousePosition({
        position: null
      });
      setGraphTooltipData(null);

      return;
    }

    const date = timeValue.timeTick;
    const displayedMetricIds = pluck('metric_id', lines);
    const filteredMetricsValue = pick(displayedMetricIds, timeValue);
    const areAllValuesEmpty = pipe(values, all(isNil))(filteredMetricsValue);

    const linesData = getLinesForMetrics({
      lines,
      metricIds: keys(filteredMetricsValue).map(Number)
    });

    if (areAllValuesEmpty) {
      changeMousePosition({ position: pointPosition });
      setGraphTooltipData(null);

      return;
    }

    const distanceWithPointPositionPerMetric = reduce(
      (acc, [metricId, value]) => {
        if (isNil(value)) {
          return acc;
        }

        const lineData = getLineForMetric({
          lines,
          metric_id: Number(metricId)
        });
        const yScale = getYScale({
          invert: (lineData as Line).invert,
          unit: (lineData as Line).unit,
          yScalesPerUnit
        });

        const y0 = yScale(value);

        const diffBetweenY0AndPointPosition = Math.abs(
          y0 + margin.top - pointPosition[1]
        );

        return {
          ...acc,
          [metricId]: diffBetweenY0AndPointPosition
        };
      },
      {},
      Object.entries(filteredMetricsValue)
    );

    const nearestY0 = Math.min(...values(distanceWithPointPositionPerMetric));

    const nearestLine = pipe(
      toPairs,
      find(([, y0]) => equals(y0, nearestY0)) as () => [string, number]
    )(distanceWithPointPositionPerMetric);

    changeMousePosition({ position: pointPosition });
    setGraphTooltipData({
      date,
      highlightedMetricId: Number(nearestLine[0]),
      metrics: map(
        ({ metric_id, color, unit, legend, name, invert }) => ({
          color,
          id: metric_id,
          name: formatMetricName({ legend, name }),
          unit,
          value: invert
            ? negate(timeValue?.[metric_id])
            : timeValue?.[metric_id]
        }),
        linesData
      ).filter(({ value }) => !isNil(value))
    });
  };

  return (
    <g>
      {displayZoomPreview && (
        <ZoomPreview
          {...zoomData}
          graphHeight={graphHeight}
          graphWidth={graphWidth}
          xScale={xScale}
        />
      )}
      {displayEventAnnotations && (
        <Annotations
          data={annotationData?.data as Array<TimelineEvent>}
          graphHeight={graphHeight}
          graphSvgRef={graphSvgRef}
          graphWidth={graphWidth}
          xScale={xScale}
        />
      )}
      {displayTimeShiftZones && (
        <TimeShiftZones
          graphHeight={graphHeight}
          graphWidth={graphWidth}
          {...timeShiftZonesData}
        />
      )}
      <Bar
        className={classes.overlay}
        data-testid="graph-interaction-zone"
        fill="transparent"
        height={graphHeight - margin.bottom}
        width={graphWidth}
        x={0}
        y={0}
        onMouseDown={mouseDown}
        onMouseLeave={mouseLeave}
        onMouseMove={mouseMove}
        onMouseUp={mouseUp}
      />
    </g>
  );
};

export default InteractionWithGraph;
