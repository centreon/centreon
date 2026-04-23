import { Event, Tooltip } from '@visx/visx';
import type { ScaleLinear } from 'd3-scale';
import { useAtomValue } from 'jotai';
import { useEffect } from 'react';

import type { TimeValue } from '../../../common/timeSeries/models';
import { getDate } from '../../helpers/index';
import { eventMouseUpAtom } from '../interactionWithGraphAtoms';
import { applyingZoomAtomAtom } from '../ZoomPreview/zoomPreviewAtoms';
import { type GraphTooltip, width } from './models';

interface Props {
  graphWidth: number;
  timeSeries: Array<TimeValue>;
  tooltipWidth?: number;
  xScale: ScaleLinear<number, number>;
}

const useGraphTooltip = ({
  graphWidth,
  tooltipWidth = width,
  timeSeries,
  xScale
}: Props): GraphTooltip => {
  const {
    tooltipLeft,
    tooltipTop,
    tooltipOpen,
    showTooltip,
    hideTooltip,
    tooltipData
  } = Tooltip.useTooltip();

  const mouseUpEvent = useAtomValue(eventMouseUpAtom);
  const isZoomApplied = useAtomValue(applyingZoomAtomAtom);

  useEffect(() => {
    if (!mouseUpEvent || isZoomApplied) {
      return;
    }

    const { x, y } = Event.localPoint(mouseUpEvent) || {
      x: 0,
      y: 0
    };

    const displayLeft = graphWidth - x < tooltipWidth;

    showTooltip({
      tooltipData: getDate({ positionX: x, timeSeries, xScale }),
      tooltipLeft: displayLeft ? x - tooltipWidth : x,
      tooltipTop: y
    });
  }, [
    mouseUpEvent,
    graphWidth,
    isZoomApplied,
    showTooltip,
    timeSeries,
    tooltipWidth,
    xScale
  ]);

  return {
    hideTooltip,
    tooltipData,
    tooltipLeft,
    tooltipOpen,
    tooltipTop
  } as GraphTooltip;
};

export default useGraphTooltip;
