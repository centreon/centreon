import { useEffect, useState } from 'react';

import { Event } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { useUpdateAtom } from 'jotai/utils';
import { equals, gte, isNil, lt } from 'ramda';

import { margin } from '../../common';

import { ZoomParametersAtom } from './zoomPreviewAtoms';

interface Boundaries {
  end: number;
  start: number;
}
interface ZoomPreview {
  zoomBarWidth: number;
  zoomBoundaries: Boundaries | null;
}

interface Props {
  eventMouseDown?: MouseEvent;
  graphWidth: number;
  movingMouseX?: number;
  xScale: ScaleTime<number, number>;
}

const useZoomPreview = ({
  eventMouseDown,
  movingMouseX,
  xScale,
  graphWidth
}: Props): ZoomPreview => {
  const [zoomBoundaries, setZoomBoundaries] = useState<Boundaries | null>(null);
  const setZoomParameters = useUpdateAtom(ZoomParametersAtom);

  const mousePoint = eventMouseDown ? Event.localPoint(eventMouseDown) : null;

  const mouseDownX = mousePoint ? mousePoint.x - margin.left : null;

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
    if (isNil(eventMouseDown)) {
      applyZoom();
      setZoomBoundaries(null);

      return;
    }
    if (!isNil(movingMouseX) || isNil(mouseDownX)) {
      return;
    }

    setZoomBoundaries({
      end: mouseDownX,
      start: mouseDownX
    });
  }, [eventMouseDown]);

  useEffect(() => {
    if (isNil(mouseDownX) || isNil(movingMouseX)) {
      return;
    }
    setZoomBoundaries({
      end: gte(movingMouseX, mouseDownX) ? movingMouseX : mouseDownX,
      start: lt(movingMouseX, mouseDownX) ? movingMouseX : mouseDownX
    });
  }, [movingMouseX]);

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0)
  );

  return { zoomBarWidth, zoomBoundaries };
};

export default useZoomPreview;
