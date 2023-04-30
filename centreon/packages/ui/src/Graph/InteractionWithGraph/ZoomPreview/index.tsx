import { alpha, useTheme } from '@mui/system';

import Bar from '../Bar';

import useZoomPreview from './useZoomPreview';

const ZoomPreview = ({ data }: any): JSX.Element => {
  const theme = useTheme();

  const { eventMouseDown, positionX, graphHeight, xScale, graphWidth } = data;
  const { zoomBarWidth, zoomBoundaries } = useZoomPreview({
    eventMouseDown,
    graphWidth,
    movingMouseX: positionX,
    xScale
  });

  return (
    <g>
      <Bar
        open
        fill={alpha(theme.palette.primary.main, 0.2)}
        height={graphHeight}
        stroke={alpha(theme.palette.primary.main, 0.5)}
        width={zoomBarWidth}
        x={zoomBoundaries?.start || 0}
        y={0}
      />
    </g>
  );
};

export default ZoomPreview;
