import { useState, useRef, useEffect } from 'react';

import { Zoom as VisxZoom } from '@visx/zoom';
import { RectClipPath } from '@visx/clip-path';

import { ParentSize } from '../..';
import { Button } from '../Button';

import { useZoomStyles } from './Zoom.styles';

export interface ZoomProps {
  children: JSX.Element;
  labels: {
    clear: string;
    zoomIn: string;
    zoomOut: string;
  };
  scaleMax?: number;
  scaleMin?: number;
  showMinimap?: boolean;
}

const initialTransform = {
  scaleX: 1,
  scaleY: 1,
  skewX: 0,
  skewY: 0,
  translateX: 0,
  translateY: 0
};
const radius = 30;

const Zoom = ({
  children,
  scaleMin = 0.5,
  scaleMax = 4,
  showMinimap = false,
  labels
}: ZoomProps): JSX.Element => {
  const { classes } = useZoomStyles();

  const [contentClientRect, setContentClientRect] = useState<{
    height: number;
    width: number;
  } | null>(null);
  const [isMouseOverMinimap, setIsMouseOverMinimap] = useState(false);
  const contentRef = useRef<SVGGElement | null>(null);

  const resizeObserver = new ResizeObserver(() => {
    setContentClientRect({
      height: contentRef.current?.getBoundingClientRect().height || 0,
      width: contentRef.current?.getBoundingClientRect().width || 0
    });
  });

  const mouseEntersMinimap = (): void => {
    setIsMouseOverMinimap(true);
  };

  const mouseLeavesMinimap = (): void => {
    setIsMouseOverMinimap(false);
  };

  useEffect(() => {
    if (contentRef.current) {
      resizeObserver.disconnect();
      setContentClientRect({
        height: contentRef.current?.getBoundingClientRect().height || 0,
        width: contentRef.current?.getBoundingClientRect().width || 0
      });
      resizeObserver.observe(contentRef.current);
    }

    return () => {
      resizeObserver.disconnect();
    };
  }, [contentRef.current]);

  return (
    <ParentSize>
      {({ width, height }) => (
        <VisxZoom<SVGSVGElement>
          height={height}
          initialTransformMatrix={initialTransform}
          scaleXMax={scaleMax}
          scaleXMin={scaleMin}
          scaleYMax={scaleMax}
          scaleYMin={scaleMin}
          width={width}
        >
          {(zoom) => (
            <div style={{ position: 'relative' }}>
              <svg
                className={classes.svg}
                data-is-grabbing={zoom.isDragging}
                height={height}
                ref={zoom.containerRef}
                width={width}
              >
                <RectClipPath
                  height={Math.max(contentClientRect?.height || 0, height)}
                  id="zoom-clip"
                  rx={radius}
                  width={Math.max(contentClientRect?.width || 0, width)}
                />
                <g
                  data-testid="zoom-content"
                  ref={contentRef}
                  transform={zoom.toString()}
                >
                  {children}
                </g>
                {showMinimap && (
                  <g
                    className={classes.minimap}
                    clipPath="url(#zoom-clip)"
                    onMouseLeave={mouseLeavesMinimap}
                    onMouseOver={mouseEntersMinimap}
                  >
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
                  </g>
                )}
              </svg>
              <div
                className={classes.actions}
                style={{
                  top: showMinimap
                    ? Math.max(contentClientRect?.height || 0, height) *
                      (isMouseOverMinimap ? 0.28 : 0.18)
                    : 8
                }}
              >
                <Button
                  data-testid="zoom in"
                  size="small"
                  variant="secondary"
                  onClick={() => zoom.scale({ scaleX: 1.2, scaleY: 1.2 })}
                >
                  {labels.zoomIn}
                </Button>
                <Button
                  data-testid="zoom out"
                  size="small"
                  variant="secondary"
                  onClick={() => zoom.scale({ scaleX: 0.8, scaleY: 0.8 })}
                >
                  {labels.zoomOut}
                </Button>
                <Button
                  data-testid="clear"
                  size="small"
                  variant="secondary"
                  onClick={zoom.clear}
                >
                  {labels.clear}
                </Button>
              </div>
            </div>
          )}
        </VisxZoom>
      )}
    </ParentSize>
  );
};

export default Zoom;
