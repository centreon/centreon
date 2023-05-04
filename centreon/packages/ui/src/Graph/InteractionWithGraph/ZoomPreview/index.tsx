import { omit } from 'ramda';

import { alpha, useTheme } from '@mui/system';

import Bar from '../Bar';

import useZoomPreview from './useZoomPreview';
import { ZoomPreviewData } from './models';

const ZoomPreview = (data: ZoomPreviewData): JSX.Element => {
  const theme = useTheme();

  const { graphHeight, xScale, graphWidth, graphSvgRef, ...rest } = data;

  const { zoomBarWidth, zoomBoundaries } = useZoomPreview({
    graphSvgRef,
    graphWidth,
    xScale
  });

  const restData = omit(['display'], { ...rest });

  return (
    <g>
      <Bar
        fill={alpha(theme.palette.primary.main, 0.2)}
        height={graphHeight}
        stroke={alpha(theme.palette.primary.main, 0.5)}
        width={zoomBarWidth}
        x={zoomBoundaries?.start || 0}
        y={0}
        {...restData}
      />
    </g>
  );
};

export default ZoomPreview;
