import { Zoom as VisxZoom } from '@visx/zoom';

import { ParentSize } from '../..';

import ZoomContent from './ZoomContent';
import { MinimapPosition } from './models';

export interface ZoomProps {
  children: JSX.Element | (({ width, height }) => JSX.Element);
  id?: number | string;
  minimapPosition?: MinimapPosition;
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

const Zoom = ({
  children,
  scaleMin = 0.5,
  scaleMax = 4,
  showMinimap = false,
  minimapPosition = 'top-left',
  id = 0
}: ZoomProps): JSX.Element => {
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
