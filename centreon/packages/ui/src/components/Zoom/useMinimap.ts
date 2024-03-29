import { useCallback, useState } from 'react';

import { ProvidedZoom, Translate } from '@visx/zoom/lib/types';
import { equals, gt, isNil, pick } from 'ramda';
import { Point } from '@visx/point';

import { ZoomState } from './models';

export interface UseMinimapProps {
  height: number;
  isDraggingFromContainer: boolean;
  minimapScale: number;
  scale: number;
  width: number;
  zoom: ProvidedZoom<SVGSVGElement> & ZoomState;
}

interface UseMinimapState {
  dragEnd: (e) => void;
  dragStart: (e) => void;
  move: (e) => void;
  transformTo: (e) => void;
  zoomInOut: (e) => void;
}

export const useMinimap = ({
  width,
  height,
  zoom,
  minimapScale,
  isDraggingFromContainer,
  scale
}: UseMinimapProps): UseMinimapState => {
  const [startPoint, setStartPoint] = useState<Pick<Point, 'x' | 'y'> | null>(
    null
  );
  const [startTranslate, setStartTranslate] = useState<Translate | null>(null);

  const getMatrixPoint = useCallback(
    (event): { x: number; y: number } => {
      const hasScale = scale > 1;
      const point = {
        x: event.nativeEvent.offsetX * (1 / minimapScale),
        y: event.nativeEvent.offsetY * (1 / minimapScale)
      };

      const dx = -(point.x * zoom.transformMatrix.scaleX - width / 2);
      const dy = -(point.y * zoom.transformMatrix.scaleY - height / 2);

      return {
        x: !hasScale ? dx : dx * scale - width / 2,
        y: !hasScale ? dy : dy * scale - height / 2
      };
    },
    [zoom.transformMatrix, scale, width, height, minimapScale]
  );

  const transformTo = useCallback(
    (e): void => {
      if (!isNil(e.nativeEvent.which) && !equals(e.nativeEvent.which, 1)) {
        return;
      }
      const { x, y } = getMatrixPoint(e);
      zoom.setTransformMatrix({
        ...zoom.transformMatrix,
        translateX: x,
        translateY: y
      });
    },
    [zoom.transformMatrix, scale]
  );

  const dragStart = (e): void => {
    if (
      (!equals(e.buttons, 0) && !equals(e.nativeEvent.which, 1)) ||
      isDraggingFromContainer
    ) {
      return;
    }
    setStartPoint(getMatrixPoint(e));
    setStartTranslate(pick(['translateX', 'translateY'], zoom.transformMatrix));
  };

  const dragEnd = (): void => {
    setStartPoint(null);
    setStartTranslate(null);
  };

  const move = useCallback(
    (e): void => {
      if (!startPoint || !startTranslate) {
        return;
      }
      const { x, y } = getMatrixPoint(e);

      const diffX = startPoint.x - x;
      const diffY = startPoint.y - y;

      zoom.setTransformMatrix({
        ...zoom.transformMatrix,
        translateX: startTranslate.translateX - diffX,
        translateY: startTranslate.translateY - diffY
      });
    },

    [zoom.transformMatrix, isDraggingFromContainer, scale, startPoint]
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
    dragEnd,
    dragStart,
    move,
    transformTo,
    zoomInOut
  };
};
