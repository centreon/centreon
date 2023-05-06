import { useEffect } from 'react';

import { omit } from 'ramda';

import { alpha, useTheme } from '@mui/system';

import Bar from '../Bar';

import useZoomPreview from './useZoomPreview';
import { ZoomPreviewData } from './models';

const ZoomPreview = (data: ZoomPreviewData): JSX.Element => {
  const theme = useTheme();

  const {
    graphHeight,
    xScale,
    graphWidth,
    graphSvgRef,
    getZoomInterval,
    ...rest
  } = data;

  const { zoomBarWidth, zoomBoundaries, zoomParameters } = useZoomPreview({
    graphSvgRef,
    graphWidth,
    xScale
  });

  useEffect(() => {
    if (!zoomParameters) {
      return;
    }

    getZoomInterval?.(zoomParameters);
  }, [zoomParameters?.start, zoomParameters?.end]);

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
