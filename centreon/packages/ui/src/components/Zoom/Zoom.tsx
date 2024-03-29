import { useState, useRef, useEffect } from 'react';

import { Zoom as VisxZoom } from '@visx/zoom';
import { RectClipPath } from '@visx/clip-path';
import { Group } from '@visx/group';
import { equals, type } from 'ramda';

import ZoomInIcon from '@mui/icons-material/Add';
import ZoomOutIcon from '@mui/icons-material/Remove';

import { ParentSize } from '../..';
import { Button, IconButton } from '../Button';

import { useZoomStyles } from './Zoom.styles';
import Minimap from './Minimap';
import { useZoom } from './useZoom';
import { minimapScale } from './constants';
import ZoomContent from './ZoomContent';

export interface ZoomProps {
  children: JSX.Element | (({ width, height }) => JSX.Element);
  labels: {
    clear: string;
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
              labels={labels}
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
