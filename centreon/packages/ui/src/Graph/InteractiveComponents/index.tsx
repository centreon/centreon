import { MutableRefObject } from 'react';

import { Event } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { useSetAtom, useAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import { isEmpty, isNil } from 'ramda';

import {
  AnnotationEvent,
  GraphInterval,
  InteractedZone,
  InteractedZone as ZoomPreviewModel
} from '../models';
import { getTimeValue } from '../timeSeries';
import { TimeValue } from '../timeSeries/models';

import Bar from './Bar';
import TimeShiftZones from './TimeShiftZones';
import ZoomPreview from './ZoomPreview';
import {
  eventMouseDownAtom,
  eventMouseUpAtom,
  eventMouseLeaveAtom,
  changeMousePositionAndTimeValueDerivedAtom,
  MousePosition
} from './interactionWithGraphAtoms';
import Annotations from './Annotations';
import { TimelineEvent } from './Annotations/models';

const useStyles = makeStyles()(() => ({
  overlay: {
    cursor: 'crosshair'
  }
}));

interface CommonData {
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  timeSeries: Array<TimeValue>;
  xScale: ScaleTime<number, number>;
}

interface TimeShiftZonesData extends InteractedZone {
  graphInterval: GraphInterval;
  loading: boolean;
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

  const [eventMouseDown, setEventMouseDown] = useAtom(eventMouseDownAtom);
  const setEventMouseUp = useSetAtom(eventMouseUpAtom);
  const setEventMouseLeave = useSetAtom(eventMouseLeaveAtom);

  const changeMousePositionAndTimeValue = useSetAtom(
    changeMousePositionAndTimeValueDerivedAtom
  );

  const { graphHeight, graphWidth, graphSvgRef, xScale, timeSeries } =
    commonData;

  const displayZoomPreview =
    (zoomData?.enable ?? true) && !isNil(eventMouseDown);
  const displayEventAnnotations =
    !isNil(annotationData?.data) && !isEmpty(annotationData?.data);

  const mouseLeave = (event): void => {
    setEventMouseLeave(event);
    setEventMouseDown(null);
    updateMousePosition(null);
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
      changeMousePositionAndTimeValue({
        position: null,
        timeValue: null
      });

      return;
    }
    const timeValue = getTimeValue({
      timeSeries,
      x: pointPosition[0],
      xScale
    });

    changeMousePositionAndTimeValue({ position: pointPosition, timeValue });
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
      <TimeShiftZones
        graphHeight={graphHeight}
        graphWidth={graphWidth}
        {...timeShiftZonesData}
      />
      <Bar
        className={classes.overlay}
        fill="transparent"
        height={graphHeight}
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
