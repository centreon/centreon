import { alpha, useTheme } from '@mui/system';

import ZoomPreview from './ZoomPreview';
import useZoomPreview from './ZoomPreview/useZoomPreview';

const InteractionWithGraph = ({ zoomPreviewData }: any): JSX.Element => {
  const theme = useTheme();

  const { eventMouseDown, positionX, graphHeight, xScale, graphWidth } =
    zoomPreviewData;
  const { zoomBarWidth, zoomBoundaries } = useZoomPreview({
    eventMouseDown,
    graphWidth,
    movingMouseX: positionX,
    xScale
  });

  return (
    <ZoomPreview
      open
      fill={alpha(theme.palette.primary.main, 0.2)}
      height={graphHeight}
      stroke={alpha(theme.palette.primary.main, 0.5)}
      width={zoomBarWidth}
      x={zoomBoundaries?.start || 0}
      y={0}
    />
  );
};

export default InteractionWithGraph;
