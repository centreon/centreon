import { useEffect } from 'react';

import { ScaleLinear } from 'd3-scale';
import { equals } from 'ramda';

import { Paper } from '@mui/material';

import { Tooltip } from '../../models';
import { TimeValue } from '../../timeSeries/models';

import useGraphTooltip from './useGraphTooltip';

const tooltipWidth = 165;

interface Props extends Tooltip {
  graphWidth: number;
  timeSeries: Array<TimeValue>;
  xScale: ScaleLinear<number, number>;
}

const GraphTooltip = ({
  graphWidth,
  xScale,
  timeSeries,
  enable = true,
  renderComponent
}: Props): JSX.Element | null => {
  const { hideTooltip, tooltipLeft, tooltipOpen, tooltipTop, tooltipData } =
    useGraphTooltip({ graphWidth, timeSeries, tooltipWidth, xScale });

  const hideTooltipOnEspcapePress = (event: globalThis.KeyboardEvent): void => {
    if (equals(event.key, 'Escape')) {
      hideTooltip();
    }
  };

  useEffect(() => {
    document.addEventListener('keydown', hideTooltipOnEspcapePress, false);

    return (): void => {
      document.removeEventListener('keydown', hideTooltipOnEspcapePress, false);
    };
  }, []);

  if (!enable) {
    return null;
  }

  if (!tooltipOpen) {
    return null;
  }

  return (
    <Paper
      style={{
        left: tooltipLeft,
        position: 'absolute',
        top: tooltipTop,
        width: tooltipWidth
      }}
    >
      {renderComponent?.({
        data: tooltipData,
        hideTooltip,
        tooltipOpen
      })}
    </Paper>
  );
};

export default GraphTooltip;
