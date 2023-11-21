import { useEffect, useState } from 'react';

import { Event } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, gte, isNil, lt } from 'ramda';

import { margin } from '../../common';
import { Interval } from '../../models';
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
}

const useZoomPreview = ({
  xScale,
  graphWidth,
  getInterval
}: Props): ZoomPreview => {
  const [zoomBoundaries, setZoomBoundaries] = useState<Boundaries | null>(null);
  const eventMouseDown = useAtomValue(eventMouseDownAtom);
  const eventMouseUp = useAtomValue(eventMouseUpAtom);
  const mousePosition = useAtomValue(mousePositionAtom);
  const setApplyingZoom = useSetAtom(applyingZoomAtomAtom);

  const mousePointDown = eventMouseDown
    ? Event.localPoint(eventMouseDown)
    : null;

  const mouseDownPositionX = mousePointDown
    ? mousePointDown.x - margin.left
    : null;

  const movingMousePositionX = mousePosition
    ? mousePosition[0] - margin.left
    : null;

  const applyZoom = (): void => {
    getInterval?.({
      end: xScale?.invert(zoomBoundaries?.end || graphWidth),
      start: xScale?.invert(zoomBoundaries?.start || 0)
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
    if (equals(zoomBoundaries.start, zoomBoundaries.end)) {
      return;
    }
    applyZoom();
    setApplyingZoom(false);
    setZoomBoundaries(null);
  }, [eventMouseUp]);

  useEffect(() => {
    if (isNil(zoomBoundaries)) {
      return;
    }
    if (equals(zoomBoundaries.start, zoomBoundaries.end)) {
      return;
    }
    setApplyingZoom(true);
  }, [zoomBoundaries]);

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0)
  );

  return { zoomBarWidth, zoomBoundaries };
};

export default useZoomPreview;
