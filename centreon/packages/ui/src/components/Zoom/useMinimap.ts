import { useCallback, useState } from 'react';

import { Point } from '@visx/point';
import { ProvidedZoom, Translate } from '@visx/zoom/lib/types';
import { equals, gt, isNil, pick } from 'ramda';

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
    (event, newScale?: number): { x: number; y: number } => {
      const hasScale = scale > 1;
      const point = {
        x: event.nativeEvent.offsetX * (1 / minimapScale),
        y: event.nativeEvent.offsetY * (1 / minimapScale)
      };

      const dx = -(
        point.x * (newScale || zoom.transformMatrix.scaleX) -
        width / 2
      );
      const dy = -(
        point.y * (newScale || zoom.transformMatrix.scaleY) -
        height / 2
      );

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
      (!isNil(e.nativeEvent.which) && !equals(e.nativeEvent.which, 1)) ||
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

      const newScaleX = isZoomIn
        ? zoom.transformMatrix.scaleX + 0.1
        : zoom.transformMatrix.scaleX - 0.1;

      const newScaleY = isZoomIn
        ? zoom.transformMatrix.scaleX + 0.1
        : zoom.transformMatrix.scaleX - 0.1;
      const { x, y } = getMatrixPoint(e, newScaleX);

      const diffX = x - zoom.transformMatrix.translateX;
      const diffY = y - zoom.transformMatrix.translateY;

      zoom.setTransformMatrix({
        ...zoom.transformMatrix,
        scaleX: newScaleX,
        scaleY: newScaleY,
        translateX: zoom.transformMatrix.translateX + diffX / 4,
        translateY: zoom.transformMatrix.translateY + diffY / 4
      });
    },
    [
      zoom.transformMatrix,
      width,
      height,
      isDraggingFromContainer,
      scale,
      startPoint
    ]
  );

  return {
    dragEnd,
    dragStart,
    move,
    transformTo,
    zoomInOut
  };
};
