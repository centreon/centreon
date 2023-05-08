import { useEffect } from 'react';

import { ScaleLinear } from 'd3-scale';
import { Event, Tooltip } from '@visx/visx';
import { useAtomValue } from 'jotai';

import { getTimeValue } from '../../timeSeries';
import { eventMouseUpAtom } from '../interactionWithGraphAtoms';
import { TimeValue } from '../../timeSeries/models';

import { GraphTooltip, width } from './models';

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

  const getDate = (positionX): Date => {
    const { timeTick } = getTimeValue({
      timeSeries,
      x: positionX,
      xScale
    });

    return new Date(timeTick);
  };

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
      tooltipData: getDate(x),
      tooltipLeft: displayLeft ? x - tooltipWidth : x,
      tooltipTop: y
    });
  }, [mouseUpEvent]);

  return {
    hideTooltip,
    tooltipData,
    tooltipLeft,
    tooltipOpen,
    tooltipTop
  } as GraphTooltip;
};

export default useGraphTooltip;
