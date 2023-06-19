import { useEffect } from 'react';

import { Tooltip } from '@visx/visx';
import { isNil } from 'ramda';

import { useLocaleDateTimeFormat } from '@centreon/ui';

import { margin, timeFormat } from '../../common/index';

import { TooltipAnchorModel, UseTooltipAnchorPointResult } from './models';
import useTickGraph from './useTickGraph';

const useTooltipAnchorPoint = ({
  timeSeries,
  xScale,
  graphHeight,
  leftScale,
  rightScale,
  graphWidth,
  lines,
  baseAxis
}: TooltipAnchorModel): UseTooltipAnchorPointResult => {
  const { format } = useLocaleDateTimeFormat();

  const { positionX, positionY, tickAxisBottom, tickAxisLeft, tickAxisRight } =
    useTickGraph({
      baseAxis,
      leftScale,
      lines,
      rightScale,
      timeSeries,
      xScale
    });

  const {
    showTooltip: showTooltipAxisYLeft,
    tooltipData: tooltipDataAxisYLeft,
    tooltipLeft: tooltipLeftAxisYLeft,
    tooltipTop: tooltipTopAxisYLeft
  } = Tooltip.useTooltip();
  const {
    showTooltip: showTooltipAxisX,
    tooltipData: tooltipDataAxisX,
    tooltipLeft: tooltipLeftAxisX,
    tooltipTop: tooltipTopAxisX
  } = Tooltip.useTooltip();

  const {
    showTooltip: showTooltipAxisYRight,
    tooltipData: tooltipDataAxisYRight,
    tooltipLeft: tooltipLeftAxisYRight,
    tooltipTop: tooltipTopAxisYRight
  } = Tooltip.useTooltip();

  useEffect(() => {
    if (!positionX || !positionY || !tickAxisBottom) {
      return;
    }

    const dataAxisX = format({
      date: tickAxisBottom,
      formatString: timeFormat
    });

    showTooltipAxisX({
      tooltipData: dataAxisX,
      tooltipLeft: positionX + margin.left / 2,
      tooltipTop: graphHeight + margin.top / 2
    });
  }, [positionX, positionY, tickAxisBottom]);

  useEffect(() => {
    if (!positionX || !positionY || !tickAxisLeft) {
      return;
    }
    showTooltipAxisYLeft({
      tooltipData: tickAxisLeft,
      tooltipLeft: 40,
      tooltipTop: positionY + margin.top / 2
    });
  }, [tickAxisLeft, positionX, positionY]);

  useEffect(() => {
    if (!positionX || !positionY || !tickAxisRight) {
      return;
    }
    showTooltipAxisYRight({
      tooltipData: tickAxisRight,
      tooltipLeft: graphWidth ? graphWidth + 40 : 0,
      tooltipTop: positionY + margin.top / 2
    });
  }, [positionX, positionY, tickAxisRight]);

  return {
    tooltipDataAxisX: !isNil(tickAxisBottom) ? tooltipDataAxisX : null,
    tooltipDataAxisYLeft: !isNil(tickAxisLeft) ? tooltipDataAxisYLeft : null,
    tooltipDataAxisYRight: !isNil(tickAxisRight) ? tooltipDataAxisYRight : null,
    tooltipLeftAxisX,
    tooltipLeftAxisYLeft,
    tooltipLeftAxisYRight,
    tooltipTopAxisX,
    tooltipTopAxisYLeft,
    tooltipTopAxisYRight
  } as UseTooltipAnchorPointResult;
};

export default useTooltipAnchorPoint;
