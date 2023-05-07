import { useEffect } from 'react';

import { Event, Tooltip } from '@visx/visx';
import { useAtomValue } from 'jotai';

import { eventMouseUpAtom } from '../interactionWithGraphAtoms';
import { zoomParametersAtom } from '../ZoomPreview/zoomPreviewAtoms';

interface Props {
  graphWidth: number;
  tooltipWidth: number;
}

const useGraphTooltip = ({ graphWidth, tooltipWidth }: Props): any => {
  const { tooltipLeft, tooltipTop, tooltipOpen, showTooltip, hideTooltip } =
    Tooltip.useTooltip();

  const mouseUpEvent = useAtomValue(eventMouseUpAtom);
  const zoomParameters = useAtomValue(zoomParametersAtom);

  useEffect(() => {
    if (!mouseUpEvent) {
      return;
    }

    const { x, y } = Event.localPoint(mouseUpEvent) || {
      x: 0,
      y: 0
    };

    const displayLeft = graphWidth - x < tooltipWidth;

    showTooltip({
      tooltipLeft: displayLeft ? x - tooltipWidth : x,
      tooltipTop: y
    });
  }, [mouseUpEvent, zoomParameters]);

  return { hideTooltip, tooltipLeft, tooltipOpen, tooltipTop };
};

export default useGraphTooltip;
