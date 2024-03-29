import { useMemo } from 'react';

import { minimapScale, radius } from './constants';
import { UseMinimapProps, useMinimap } from './useMinimap';
import { useZoomStyles } from './Zoom.styles';

interface Props extends Omit<UseMinimapProps, 'minimapScale'> {
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

  const { transformTo, move, zoomInOut } = useMinimap({
    height,
    isDraggingFromContainer,
    minimapScale,
    width,
    zoom
  });

  const yMinimapScale = useMemo(
    () => contentClientRect.height / zoom.transformMatrix.scaleY / height,
    [contentClientRect.height, height]
  );
  const xMinimapScale = useMemo(
    () => contentClientRect.width / zoom.transformMatrix.scaleX / width,
    [contentClientRect.width, width]
  );
  const scale = 1 / Math.max(yMinimapScale, xMinimapScale);

  const finalHeight = height;
  const finalWidth = width;

  const toStringInvert = (): string => {
    const { translateX, translateY, scaleX, scaleY, skewX, skewY } =
      zoom.invert();

    return `matrix(${scaleX}, ${skewY}, ${skewX}, ${scaleY}, ${translateX}, ${translateY})`;
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
          transform: `scale(${(scale > 1 ? 1 : scale) || 1})`
        }}
      >
        {children}
      </g>
      <rect
        className={classes.minimapZoom}
        fillOpacity={0.2}
        height={height}
        rx={radius}
        transform={toStringInvert()}
        width={width}
      />
      <rect
        data-testid="minimap-interaction"
        fill="transparent"
        height={finalHeight}
        rx={radius}
        width={finalWidth}
        onMouseDown={transformTo}
        onMouseMove={move}
        onWheel={zoomInOut}
      />
    </g>
  );
};

export default Minimap;
