import { MutableRefObject } from 'react';

import { useSetAtom } from 'jotai';
import { makeStyles } from 'tss-react/mui';
import { ScaleTime } from 'd3-scale';

import { ZoomPreview as ZoomPreviewModel } from '../models';

import Bar from './Bar';
import ZoomPreview from './ZoomPreview';
import {
  eventMouseDownAtom,
  eventMouseMovingAtom,
  eventMouseUpAtom
} from './interactionWithGraphAtoms';
import TimeShiftZones from './TimeShiftZones';

const useStyles = makeStyles()(() => ({
  overlay: {
    cursor: 'crosshair'
  }
}));

interface CommonData {
  graphHeight: number;
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
}

interface ZoomData extends ZoomPreviewModel {
  xScale: ScaleTime<number, number>;
}

interface Props {
  commonData: CommonData;
  timeShiftZonesData: any;
  zoomData: ZoomData;
}

const InteractionWithGraph = ({
  zoomData,
  commonData,
  timeShiftZonesData
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const setEventMouseMoving = useSetAtom(eventMouseMovingAtom);
  const setEventMouseDown = useSetAtom(eventMouseDownAtom);
  const setEventMouseUp = useSetAtom(eventMouseUpAtom);

  const { graphHeight, graphWidth, graphSvgRef } = commonData;

  const displayZoomPreview = zoomData?.display ?? true;

  const mouseLeave = (): void => {
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
