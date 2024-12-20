import { Zoom as VisxZoom } from '@visx/zoom';

import type { TransformMatrix } from '@visx/zoom/lib/types';

import { ParentSize } from '../..';

import ZoomContent from './ZoomContent';
import type { ChildrenProps, MinimapPosition } from './models';

export interface ZoomProps {
  children: (args: ChildrenProps) => JSX.Element;
  id?: number | string;
  minimapPosition?: MinimapPosition;
  scaleMax?: number;
  scaleMin?: number;
  showMinimap?: boolean;
  initialTransformMatrix?: TransformMatrix
}

const initialTransform = {
  scaleX: 1,
  scaleY: 1,
  skewX: 0,
  skewY: 0,
  translateX: 0,
  translateY: 0
};

const Zoom = ({
  children,
  scaleMin = 0.5,
  scaleMax = 4,
  showMinimap = false,
  minimapPosition = 'top-left',
  id = 0,
  initialTransformMatrix = initialTransform
}: ZoomProps): JSX.Element => {
  return (
    <ParentSize>
      {({ width, height }) => (
        <VisxZoom<SVGSVGElement>
          height={height}
          initialTransformMatrix={initialTransformMatrix}
          scaleXMax={scaleMax}
          scaleXMin={scaleMin}
          scaleYMax={scaleMax}
          scaleYMin={scaleMin}
          width={width}
        >
          {(zoom) => (
            <ZoomContent
              height={height}
              id={id}
              minimapPosition={minimapPosition}
              showMinimap={showMinimap}
              width={width}
              zoom={zoom}
            >
              {children}
            </ZoomContent>
          )}
        </VisxZoom>
      )}
    </ParentSize>
  );
};

export default Zoom;
