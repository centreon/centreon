import { useCallback } from 'react';

import { ProvidedZoom } from '@visx/zoom/lib/types';
import { equals, gt } from 'ramda';

import { ZoomState } from './models';

export interface UseMinimapProps {
  height: number;
  isDraggingFromContainer: boolean;
  minimapScale: number;
  width: number;
  zoom: ProvidedZoom<SVGSVGElement> & ZoomState;
}

interface UseMinimapState {
  move: (e) => void;
  transformTo: (e) => void;
  zoomInOut: (e) => void;
}

export const useMinimap = ({
  width,
  height,
  zoom,
  minimapScale,
  isDraggingFromContainer
}: UseMinimapProps): UseMinimapState => {
  const getMatrixPoint = useCallback(
    (event): { x: number; y: number } => {
      const point = {
        x: event.nativeEvent.offsetX * (1 / minimapScale),
        y: event.nativeEvent.offsetY * (1 / minimapScale)
      };

      return {
        x: -(point.x * zoom.transformMatrix.scaleX - width / 2),
        y: -(point.y * zoom.transformMatrix.scaleY - height / 2)
      };
    },
    [zoom.transformMatrix]
  );

  const transformTo = useCallback(
    (e): void => {
      if (!equals(e.nativeEvent.which, 1)) {
        return;
      }
      const { x, y } = getMatrixPoint(e);
      zoom.setTransformMatrix({
        ...zoom.transformMatrix,
        translateX: x,
        translateY: y
      });
    },
    [zoom.transformMatrix]
  );

  const move = useCallback(
    (e): void => {
      if (!equals(e.nativeEvent.which, 1) || isDraggingFromContainer) {
        return;
      }
      transformTo(e);
    },
    [zoom.transformMatrix, isDraggingFromContainer]
  );

  const zoomInOut = useCallback(
    (e): void => {
      const isZoomIn = gt(0, e.deltaY);
      const { x, y } = getMatrixPoint(e);

      zoom.setTransformMatrix({
        ...zoom.transformMatrix,
        scaleX: isZoomIn
          ? zoom.transformMatrix.scaleX + 0.1
          : zoom.transformMatrix.scaleX - 0.1,
        scaleY: isZoomIn
          ? zoom.transformMatrix.scaleY + 0.1
          : zoom.transformMatrix.scaleY - 0.1,
        translateX: x,
        translateY: y
      });
    },
    [zoom.transformMatrix]
  );

  return {
    move,
    transformTo,
    zoomInOut
  };
};
