import { MutableRefObject, useEffect, useState } from 'react';

import { Event } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
import { equals, gte, isNil, lt } from 'ramda';

import { margin } from '../../common';
import {
  eventMouseDownAtom,
  eventMouseMovingAtom,
  eventMouseUpAtom
} from '../interactionWithGraphAtoms';

import { zoomParametersAtom } from './zoomPreviewAtoms';

interface Boundaries {
  end: number;
  start: number;
}
interface ZoomPreview {
  zoomBarWidth: number;
  zoomBoundaries: Boundaries | null;
}

interface Props {
  graphSvgRef: MutableRefObject<SVGSVGElement | null>;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
}

const useZoomPreview = ({
  graphSvgRef,
  xScale,
  graphWidth
}: Props): ZoomPreview => {
  const [zoomBoundaries, setZoomBoundaries] = useState<Boundaries | null>(null);
  const eventMouseMoving = useAtomValue(eventMouseMovingAtom);
  const eventMouseDown = useAtomValue(eventMouseDownAtom);
  const eventMouseUp = useAtomValue(eventMouseUpAtom);

  const setZoomParameters = useUpdateAtom(zoomParametersAtom);

  const mousePointDown = eventMouseDown
    ? Event.localPoint(eventMouseDown)
    : null;

  const mouseDownPositionX = mousePointDown
    ? mousePointDown.x - margin.left
    : null;

  const mousePositionMoving = eventMouseMoving
    ? Event.localPoint(graphSvgRef.current as SVGSVGElement, eventMouseMoving)
    : null;

  const movingMousePositionX = mousePositionMoving
    ? mousePositionMoving.x - margin.left
    : null;

  const applyZoom = (): void => {
    if (equals(zoomBoundaries?.start, zoomBoundaries?.end)) {
      return;
    }

    setZoomParameters({
      end: xScale?.invert(zoomBoundaries?.end || graphWidth)?.toISOString(),
      start: xScale?.invert(zoomBoundaries?.start || 0)?.toISOString()
    });
  };

  useEffect(() => {
    if (isNil(mouseDownPositionX) || isNil(movingMousePositionX)) {
      return;
    }

    setZoomBoundaries({
      end: gte(movingMousePositionX, mouseDownPositionX)
        ? movingMousePositionX
        : mouseDownPositionX,
      start: lt(movingMousePositionX, mouseDownPositionX)
        ? movingMousePositionX
        : mouseDownPositionX
    });
  }, [movingMousePositionX, mouseDownPositionX]);

  useEffect(() => {
    if (isNil(eventMouseUp) || isNil(zoomBoundaries)) {
      return;
    }
    applyZoom();
    setZoomBoundaries(null);
  }, [eventMouseUp]);

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0)
  );

  return { zoomBarWidth, zoomBoundaries };
};

export default useZoomPreview;
