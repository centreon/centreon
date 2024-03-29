import { useEffect, useRef, useState } from 'react';

import { RectClipPath } from '@visx/clip-path';
import { equals, type } from 'ramda';
import { Group } from '@visx/group';

import ZoomInIcon from '@mui/icons-material/Add';
import ZoomOutIcon from '@mui/icons-material/Remove';

import { Button, IconButton } from '../Button';

import { minimapScale, radius } from './constants';
import { useZoom } from './useZoom';
import { useZoomStyles } from './Zoom.styles';
import Minimap from './Minimap';

const ZoomContent = ({
  zoom,
  width,
  height,
  children,
  showMinimap,
  labels
}): JSX.Element => {
  const { classes } = useZoomStyles();
  const contentRef = useRef<SVGGElement | null>(null);
  const [contentClientRect, setContentClientRect] = useState<{
    height: number;
    width: number;
  } | null>(null);

  const resizeObserver = new ResizeObserver(() => {
    setContentClientRect({
      height: (contentRef.current as SVGGElement).getBoundingClientRect()
        .height,
      width: (contentRef.current as SVGGElement).getBoundingClientRect().width
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

  const isChildrenObject = equals(type(children), 'Object');

  return (
    <div style={{ position: 'relative' }}>
      <svg
        className={classes.svg}
        data-is-grabbing={isDragging}
        data-testid="zoom-container"
        height={height}
        width={width}
        onMouseDown={dragStart(zoom)}
        onMouseMove={move(zoom)}
        onMouseUp={dragEnd}
        onWheel={zoom.handleWheel}
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
          {isChildrenObject ? children : children({ height, width })}
        </g>
      </svg>
      <svg
        className={classes.minimapContainer}
        data-testid="minimap"
        height={height * minimapScale}
        width={width * minimapScale}
      >
        {showMinimap && contentClientRect && (
          <Minimap
            contentClientRect={contentClientRect}
            height={height}
            isDraggingFromContainer={isDragging}
            width={width}
            zoom={zoom}
          >
            <Group left={0} top={contentClientRect.height / 10}>
              {isChildrenObject ? children : children({ height, width })}
            </Group>
          </Minimap>
        )}
      </svg>
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

        <Button
          data-testid="clear"
          size="small"
          variant="ghost"
          onClick={zoom.clear}
        >
          {labels.clear}
        </Button>
      </div>
    </div>
  );
};

export default ZoomContent;
