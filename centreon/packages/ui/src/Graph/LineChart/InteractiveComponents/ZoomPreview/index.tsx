import { omit } from 'ramda';

import { useTheme } from '@mui/material';
import { alpha } from '@mui/system';

import Bar from '../Bar';

import { ZoomPreviewData } from './models';
import useZoomPreview from './useZoomPreview';

const ZoomPreview = (data: ZoomPreviewData): JSX.Element => {
  const theme = useTheme();

  const { graphHeight, xScale, graphWidth, getInterval, ...rest } = data;

  const { zoomBarWidth, zoomBoundaries } = useZoomPreview({
    getInterval,
    graphWidth,
    xScale
  });

  const restData = omit(['enable'], { ...rest });

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
