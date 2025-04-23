import {
  type ForwardedRef,
  MutableRefObject,
  forwardRef,
  useEffect,
  useRef,
  useState
} from 'react';

import { RectClipPath } from '@visx/clip-path';

import ZoomInIcon from '@mui/icons-material/Add';
import ZoomOutIcon from '@mui/icons-material/Remove';
import ReplayIcon from '@mui/icons-material/Replay';

import { IconButton } from '../Button';

import Minimap from './Minimap';
import { useZoomStyles } from './Zoom.styles';
import { minimapScale, radius } from './constants';
import {
  type Dimension,
  type MinimapPosition,
  ZoomChildren,
  type ZoomInterface
} from './models';
import { useZoom } from './useZoom';

export interface Props extends Dimension, ZoomInterface, ZoomChildren {
  id?: number | string;
  minimapPosition: MinimapPosition;
  showMinimap?: boolean;
}

const ZoomContent = forwardRef(
  (
    { zoom, width, height, children, showMinimap, minimapPosition, id }: Props,
    ref?: ForwardedRef<SVGGElement | null>
  ): JSX.Element => {
    const { classes } = useZoomStyles();

    const fallbackRef = useRef<SVGGElement | null>(null);
    const contentRef = (ref ||
      fallbackRef) as MutableRefObject<SVGGElement | null>;
    const minimapSvgRef = useRef<SVGSVGElement | null>(null);
    const minimapContentRef = useRef<SVGSVGElement | null>(null);
    const [contentClientRect, setContentClientRect] =
      useState<Dimension | null>(null);

    const resizeObserver = new ResizeObserver(() => {
      const contentBoundingClientRect = (
        contentRef.current as SVGGElement
      ).getBoundingClientRect();

      setContentClientRect({
        height: contentBoundingClientRect.height,
        width: contentBoundingClientRect.width
      });
    });

    useEffect(() => {
      if (contentRef.current) {
        resizeObserver.disconnect();
        resizeObserver.observe(contentRef.current);
      }

      return () => {
        resizeObserver.disconnect();
      };
    }, [contentRef.current]);

    const { move, dragEnd, dragStart, isDragging } = useZoom();

    const diffBetweenContentAndSvg = minimapSvgRef.current &&
      minimapContentRef.current && {
        left:
          minimapContentRef.current.getBoundingClientRect().left -
          minimapSvgRef.current.getBoundingClientRect().left,
        top:
          minimapContentRef.current.getBoundingClientRect().top -
          minimapSvgRef.current.getBoundingClientRect().top
      };

    return (
      <div style={{ position: 'relative' }}>
        <svg
          className={classes.svg}
          data-is-grabbing={isDragging}
          data-testid="zoom-container"
          height={height}
          width={width}
          onMouseDown={dragStart(zoom)}
          onMouseEnter={dragStart(zoom)}
          onMouseLeave={dragEnd}
          onMouseMove={move(zoom)}
          onMouseUp={dragEnd}
          onWheel={zoom.handleWheel}
        >
          <RectClipPath
            height={Math.max(contentClientRect?.height || 0, height)}
            id={`zoom-clip-${id}`}
            rx={radius}
            width={Math.max(contentClientRect?.width || 0, width)}
          />
          <g
            data-testid="zoom-content"
            ref={contentRef}
            transform={zoom.toString()}
          >
            {children({
              contentClientRect,
              height,
              transformMatrix: zoom.transformMatrix,
              width,
              zoom
            })}
          </g>
        </svg>
        <div className={classes.actionsAndZoom} data-position={minimapPosition}>
          {showMinimap && contentClientRect && (
            <svg
              className={classes.minimapContainer}
              data-testid="minimap"
              height={height * minimapScale}
              ref={minimapSvgRef}
              width={width * minimapScale}
            >
              <Minimap
                contentClientRect={contentClientRect}
                diffBetweenContentAndSvg={
                  diffBetweenContentAndSvg || { left: 0, top: 0 }
                }
                height={height}
                id={id}
                isDraggingFromContainer={isDragging}
                width={width}
                zoom={zoom}
              >
                <g ref={minimapContentRef}>
                  {children({
                    contentClientRect,
                    height,
                    transformMatrix: zoom.transformMatrix,
                    width,
                    zoom
                  })}
                </g>
              </Minimap>
            </svg>
          )}
          <div className={classes.actions}>
            <IconButton
              data-testid="zoom in"
              icon={<ZoomInIcon />}
              size="small"
              onClick={() => zoom.scale({ scaleX: 1.2, scaleY: 1.2 })}
            />
            <IconButton
              data-testid="zoom out"
              icon={<ZoomOutIcon />}
              size="small"
              onClick={() => zoom.scale({ scaleX: 0.8, scaleY: 0.8 })}
            />
            <IconButton
              data-testid="clear"
              icon={<ReplayIcon />}
              size="small"
              onClick={zoom.reset}
            />
          </div>
        </div>
      </div>
    );
  }
);

export default ZoomContent;
