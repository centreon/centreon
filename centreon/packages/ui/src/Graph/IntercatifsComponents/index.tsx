import { MutableRefObject } from 'react';

import { ScaleTime } from 'd3-scale';
import { useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import { isEmpty, isNil } from 'ramda';

import {
  AnnotationEvent,
  GraphInterval,
  InteractedZone,
  InteractedZone as ZoomPreviewModel
} from '../models';

import Bar from './Bar';
import TimeShiftZones from './TimeShiftZones';
import ZoomPreview from './ZoomPreview';
import {
  eventMouseDownAtom,
  eventMouseMovingAtom,
  eventMouseUpAtom,
  eventMouseLeaveAtom
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
  xScale: ScaleTime<number, number>;
}

// interface ZoomData extends ZoomPreviewModel {
//   xScale: ScaleTime<number, number>;
// }
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

  const setEventMouseMoving = useSetAtom(eventMouseMovingAtom);
  const setEventMouseDown = useSetAtom(eventMouseDownAtom);
  const setEventMouseUp = useSetAtom(eventMouseUpAtom);
  const setEventMouseLeave = useSetAtom(eventMouseLeaveAtom);

  const { graphHeight, graphWidth, graphSvgRef, xScale } = commonData;

  const displayZoomPreview = zoomData?.enable ?? true;
  const displayEventAnnotations =
    !isNil(annotationData?.data) && !isEmpty(annotationData?.data);

  const mouseLeave = (event): void => {
    setEventMouseLeave(event);
    setEventMouseMoving(null);
    setEventMouseDown(null);
  };

  const mouseUp = (event): void => {
    setEventMouseUp(event);
    setEventMouseDown(null);
  };

  const mouseMove = (event): void => {
    setEventMouseMoving(event);
  };

  const mouseDown = (event): void => {
    setEventMouseDown(event);
  };

  return (
    <g>
      {displayZoomPreview && (
        <ZoomPreview
          {...zoomData}
          graphHeight={graphHeight}
          graphSvgRef={graphSvgRef}
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
