import { useMemo } from 'react';

import { scaleLinear } from '@visx/scale';

import { minimapScale, radius } from './constants';
import { UseMinimapProps, useMinimap } from './useMinimap';
import { useZoomStyles } from './Zoom.styles';

interface Props extends Omit<UseMinimapProps, 'minimapScale' | 'scale'> {
  children: JSX.Element;
  contentClientRect: {
    height: number;
    width: number;
  };
  isDraggingFromContainer: boolean;
}

const Minimap = ({
  zoom,
  children,
  height,
  width,
  contentClientRect,
  isDraggingFromContainer
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

  const getAdditionalPadding = (): number => {
    if (additionalScale > 0.05) {
      return 0;
    }

    const padding =
      additionalScale > 0.012
        ? (1 / additionalScale) * (1 / zoom.transformMatrix.scaleY)
        : 1 / additionalScale / zoom.transformMatrix.scaleY;

    if (additionalScale < 0.009) {
      const test = scaleLinear({
        clamp: true,
        domain: [0.005, 0.002],
        range: [1, 5]
      });

      return padding - padding / test(additionalScale);
    }

    return padding;
  };

  return (
    <g className={classes.minimap} clipPath="url(#zoom-clip)">
      <rect
        className={classes.minimapBackground}
        height={finalHeight}
        rx={radius}
        width={finalWidth}
      />
      <g
        style={{
          transform: `scale(${scaleToUse - additionalScale}) translate(0px, ${getAdditionalPadding()}px)`
        }}
      >
        {children}
        <g
          style={{
            transform: `translate(0px, ${contentClientRect.height / 10}px)`
          }}
        >
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
