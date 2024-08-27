import { useEffect, useRef, useState } from 'react';

import { RectClipPath } from '@visx/clip-path';
import { ProvidedZoom } from '@visx/zoom/lib/types';

import ZoomInIcon from '@mui/icons-material/Add';
import ZoomOutIcon from '@mui/icons-material/Remove';
import ReplayIcon from '@mui/icons-material/Replay';

import { IconButton } from '../Button';

import Minimap from './Minimap';
import { useZoomStyles } from './Zoom.styles';
import { minimapScale, radius } from './constants';
import { ChildrenProps, MinimapPosition, ZoomState } from './models';
import { useZoom } from './useZoom';

export interface Props {
  children: ({ width, height, transformMatrix }: ChildrenProps) => JSX.Element;
  height: number;
  id?: number | string;
  minimapPosition: MinimapPosition;
  showMinimap?: boolean;
  width: number;
  zoom: ProvidedZoom<SVGSVGElement> & ZoomState;
}

const ZoomContent = ({
  zoom,
  width,
  height,
  children,
  showMinimap,
  minimapPosition,
  id
}: Props): JSX.Element => {
  const { classes } = useZoomStyles();
  const contentRef = useRef<SVGGElement | null>(null);
  const minimapSvgRef = useRef<SVGSVGElement | null>(null);
  const minimapContentRef = useRef<SVGSVGElement | null>(null);
  const [contentClientRect, setContentClientRect] = useState<{
    height: number;
    width: number;
  } | null>(null);

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
            width
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
                  width
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
};

export default ZoomContent;
