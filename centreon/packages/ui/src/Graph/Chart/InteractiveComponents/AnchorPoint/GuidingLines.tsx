import { grey } from '@mui/material/colors';

import { Shape } from '@visx/visx';
import { ReactElement } from 'react';

import type { GuidingLines as GuidingLinesModel } from './models';
import useTickGraph from './useTickGraph';

const GuidingLines = ({
  timeSeries,
  xScale,
  graphHeight,
  graphWidth,
  maxLeftAxisCharacters,
  hasSecondUnit,
  lines,
  leftScale,
  rightScale,
  hasUnit
}: GuidingLinesModel): ReactElement | null => {
  const { positionX, positionY } = useTickGraph({
    graphHeight,
    graphWidth,
    hasSecondUnit,
    hasUnit,
    leftScale,
    lines,
    maxLeftAxisCharacters,
    rightScale,
    timeSeries,
    xScale
  });
  if (!positionX || !positionY) {
    return null;
  }

  return (
    <>
      <Shape.Line
        fill="dotted"
        from={{ x: positionX, y: 0 }}
        pointerEvents="none"
        stroke={grey[400]}
        strokeDasharray="2 4"
        strokeWidth={1}
        to={{ x: positionX, y: graphHeight }}
      />
      <Shape.Line
        from={{ x: 0, y: positionY }}
        pointerEvents="none"
        stroke={grey[400]}
        strokeDasharray="2 4"
        strokeWidth={1}
        to={{ x: graphWidth, y: positionY }}
      />
    </>
  );
};

export default GuidingLines;
