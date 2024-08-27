import { useMemo } from 'react';

import { scaleLinear } from '@visx/scale';

import { useZoomStyles } from './Zoom.styles';
import { minimapScale, radius } from './constants';
import { UseMinimapProps, useMinimap } from './useMinimap';

interface Props extends Omit<UseMinimapProps, 'minimapScale' | 'scale'> {
  children: JSX.Element;
  contentClientRect: {
    height: number;
    width: number;
  };
  diffBetweenContentAndSvg: {
    left: number;
    top: number;
  };
  id?: number | string;
  isDraggingFromContainer: boolean;
}

const Minimap = ({
  zoom,
  children,
  height,
  width,
  contentClientRect,
  isDraggingFromContainer,
  diffBetweenContentAndSvg,
  id
}: Props): JSX.Element => {
  const { classes } = useZoomStyles();

  const yMinimapScale = useMemo(
    () => contentClientRect.height / zoom.transformMatrix.scaleY / height,
    [contentClientRect.height, height]
  );
  const xMinimapScale = useMemo(
    () => contentClientRect.width / zoom.transformMatrix.scaleX / width,
    [contentClientRect.width, width]
  );

  const scale = Math.max(yMinimapScale, xMinimapScale);
  const invertedScale = 1 / scale;
  const scaleToUse = (invertedScale > 1 ? 1 : invertedScale) || 1;

  const { move, zoomInOut, dragStart, dragEnd } = useMinimap({
    height,
    isDraggingFromContainer,
    minimapScale,
    scale: (invertedScale > 1 ? 1 : scale) || 1,
    width,
    zoom
  });

  const finalHeight = height;
  const finalWidth = width;

  const additionalScaleScale = scaleLinear({
    clamp: true,
    domain: [contentClientRect.height, 0],
    range: [0, 0.05]
  });

  const additionalScale =
    additionalScaleScale(contentClientRect.height - height) /
    2 /
    zoom.transformMatrix.scaleY;

  const translateX = useMemo(
    () =>
      -diffBetweenContentAndSvg.left /
      zoom.transformMatrix.scaleX /
      minimapScale,
    [diffBetweenContentAndSvg.left]
  );
  const translateY = useMemo(
    () =>
      -diffBetweenContentAndSvg.top /
      zoom.transformMatrix.scaleX /
      minimapScale,
    [diffBetweenContentAndSvg.top]
  );

  return (
    <g className={classes.minimap} clipPath={`url(#zoom-clip-${id})`}>
      <rect
        className={classes.minimapBackground}
        height={finalHeight}
        rx={radius}
        width={finalWidth}
      />
      <g
        className={classes.movingZone}
        style={{
          transform: `scale(${scaleToUse - additionalScale}) translate(${translateX}px, ${translateY}px)`
        }}
      >
        {children}
        <g>
          <rect
            className={classes.minimapZoom}
            fillOpacity={0.2}
            height={height}
            rx={radius}
            transform={zoom.toStringInvert()}
            width={width}
          />
        </g>
      </g>
      <rect
        data-testid="minimap-interaction"
        fill="transparent"
        height={finalHeight}
        rx={radius}
        width={finalWidth}
        onMouseDown={dragStart}
        onMouseEnter={dragStart}
        onMouseLeave={dragEnd}
        onMouseMove={move}
        onMouseUp={dragEnd}
        onWheel={zoomInOut}
      />
    </g>
  );
};

export default Minimap;
