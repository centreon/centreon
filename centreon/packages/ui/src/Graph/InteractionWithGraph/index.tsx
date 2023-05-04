import { MutableRefObject } from 'react';

import { useUpdateAtom } from 'jotai/utils';
import { makeStyles } from 'tss-react/mui';

import Bar from './Bar';
import ZoomPreview from './ZoomPreview';
import { ZoomPreviewData } from './ZoomPreview/models';
import {
  eventMouseDownAtom,
  eventMouseMovingAtom,
  eventMouseUpAtom
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
}

interface Props {
  commonData: CommonData;
  zoomData: Pick<ZoomPreviewData, 'xScale'>;
}

const InteractionWithGraph = ({ zoomData, commonData }: Props): JSX.Element => {
  const { classes } = useStyles();

  const setEventMouseMoving = useUpdateAtom(eventMouseMovingAtom);
  const setEventMouseDown = useUpdateAtom(eventMouseDownAtom);
  const setEventMouseUp = useUpdateAtom(eventMouseUpAtom);

  const { graphHeight, graphWidth, graphSvgRef } = commonData;

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
      <ZoomPreview
        {...zoomData}
        graphHeight={graphHeight}
        graphSvgRef={graphSvgRef}
        graphWidth={graphWidth}
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
