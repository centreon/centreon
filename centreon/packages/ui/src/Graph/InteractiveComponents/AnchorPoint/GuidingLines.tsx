import { Shape } from '@visx/visx';

import { grey } from '@mui/material/colors';

import useTickGraph from './useTickGraph';
import { GuidingLines } from './models';

const GuidingLines = ({
  timeSeries,
  xScale,
  graphHeight,
  graphWidth
}: GuidingLines): JSX.Element => {
  const { positionX, positionY } = useTickGraph({
    timeSeries,
    xScale
  });

  return (
    <>
      <Shape.Line
        from={{ x: positionX, y: 0 }}
        pointerEvents="none"
        stroke={grey[400]}
        strokeWidth={1}
        to={{ x: positionX, y: graphHeight }}
      />
      <Shape.Line
        from={{ x: 0, y: positionY }}
        pointerEvents="none"
        stroke={grey[400]}
        strokeWidth={1}
        to={{ x: graphWidth, y: positionY }}
      />
    </>
  );
};

export default GuidingLines;
