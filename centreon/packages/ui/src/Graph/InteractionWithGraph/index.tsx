import { useState } from 'react';

import { useUpdateAtom } from 'jotai/utils';
import { makeStyles } from 'tss-react/mui';

import Bar from './Bar';
import ZoomPreview from './ZoomPreview';
import { ZoomPreviewData } from './ZoomPreview/models';
import { eventMouseMovingAtom } from './interactionWithGraphAtoms';

const useStyles = makeStyles()(() => ({
  overlay: {
    cursor: 'crosshair'
  }
}));

interface CommonData {
  graphHeight: number;
  graphWidth: number;
}

interface Props {
  commonData: CommonData;
  zoomData: Pick<ZoomPreviewData, 'positionX' | 'xScale'>;
}

const InteractionWithGraph = ({ zoomData, commonData }: Props): JSX.Element => {
  const { classes } = useStyles();

  const [eventMouseDown, setEventMouseDown] = useState<null | MouseEvent>(null);
  const setEventMouseMoving = useUpdateAtom(eventMouseMovingAtom);

  const { graphHeight, graphWidth } = commonData;

  const mouseLeave = (): void => {
    setEventMouseMoving(null);
    setEventMouseDown(null);
  };

  const mouseUp = (): void => {
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
      <ZoomPreview
        {...zoomData}
        eventMouseDown={eventMouseDown}
        graphHeight={graphHeight}
        graphWidth={graphWidth}
      />
    </g>
  );
};

export default InteractionWithGraph;
