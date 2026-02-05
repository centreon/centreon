import { Event } from '@visx/visx';
import type { ScaleTime } from 'd3-scale';
import { useAtom, useAtomValue } from 'jotai';
import { equals, gte, isNil, lt } from 'ramda';
import { type RefObject, useCallback, useEffect, useState } from 'react';

import type { Interval } from '../../models';
import {
  eventMouseDownAtom,
  eventMouseUpAtom,
  mousePositionAtom
} from '../interactionWithGraphAtoms';
import { applyingZoomAtomAtom } from './zoomPreviewAtoms';

interface Boundaries {
  end: number;
  start: number;
}
interface ZoomPreview {
  zoomBarWidth: number;
  zoomBoundaries: Boundaries | null;
}

interface Props {
  getInterval?: (args: Interval) => void;
  graphWidth: number;
  xScale: ScaleTime<number, number>;
  graphSvgRef: RefObject<SVGSVGElement | null>;
  graphMarginLeft: number;
}

const useZoomPreview = ({
  xScale,
  graphWidth,
  getInterval,
  graphSvgRef,
  graphMarginLeft
}: Props): ZoomPreview => {
  const [zoomBoundaries, setZoomBoundaries] = useState<Boundaries | null>(null);
  const [isApplyingZoom, setApplyingZoom] = useAtom(applyingZoomAtomAtom);
  const eventMouseDown = useAtomValue(eventMouseDownAtom);
  const eventMouseUp = useAtomValue(eventMouseUpAtom);
  const mousePosition = useAtomValue(mousePositionAtom);

  const mousePointDown =
    eventMouseDown && graphSvgRef.current
      ? Event.localPoint(graphSvgRef.current, eventMouseDown)
      : null;

  const mouseDownPositionX = mousePointDown
    ? mousePointDown.x - graphMarginLeft
    : null;

  const movingMousePositionX = mousePosition
    ? mousePosition[0] - graphMarginLeft
    : null;

  const applyZoom = useCallback((): void => {
    getInterval?.({
      end: xScale?.invert(zoomBoundaries?.end || graphWidth),
      start: xScale?.invert(zoomBoundaries?.start || 0)
    });
  }, [xScale, zoomBoundaries, graphWidth, getInterval]);

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
    if (equals(zoomBoundaries.start, zoomBoundaries.end)) {
      return;
    }
    applyZoom();
    setApplyingZoom(false);
  }, [eventMouseUp, applyZoom, setApplyingZoom, zoomBoundaries]);

  useEffect(() => {
    if (isNil(zoomBoundaries)) {
      return;
    }
    if (equals(zoomBoundaries.start, zoomBoundaries.end)) {
      return;
    }
    setApplyingZoom(true);
  }, [zoomBoundaries, setApplyingZoom]);

  useEffect(() => {
    if (isApplyingZoom) {
      return;
    }
    setZoomBoundaries(null);
  }, [isApplyingZoom]);

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0)
  );

  return { zoomBarWidth, zoomBoundaries };
};

export default useZoomPreview;
