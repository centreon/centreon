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

  return (
    <g className={classes.minimap} clipPath="url(#zoom-clip)">
      <rect
        className={classes.minimapBackground}
        height={Math.max(contentClientRect?.height || 0, height)}
        rx={radius}
        width={Math.max(contentClientRect?.width || 0, width)}
      />
      {children}
      <rect
        className={classes.minimapZoom}
        fillOpacity={0.2}
        height={height}
        rx={radius}
        transform={zoom.toStringInvert()}
        width={width}
      />
      <rect
        data-testid="minimap-interaction"
        fill="transparent"
        height={Math.max(contentClientRect?.height || 0, height)}
        rx={radius}
        width={Math.max(contentClientRect?.width || 0, width)}
        onMouseDown={transformTo}
        onMouseMove={move}
        onWheel={zoomInOut}
      />
    </g>
  );
};

export default Minimap;
