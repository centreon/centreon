import { useEffect } from 'react';

import { equals } from 'ramda';

import { Paper } from '@mui/material';

import { Tooltip } from '../../models';

import { GraphTooltip as GraphTooltipModel, width } from './models';

interface Props extends GraphTooltipModel, Tooltip {
  [x: string]: unknown;
}

const GraphTooltip = ({
  hideTooltip,
  tooltipLeft,
  tooltipTop,
  tooltipData,
  renderComponent,
  tooltipOpen
}: Props): JSX.Element | null => {
  const hideTooltipOnEscapePress = (event: globalThis.KeyboardEvent): void => {
    if (equals(event.key, 'Escape')) {
      hideTooltip();
    }
  };

  useEffect(() => {
    document.addEventListener('keydown', hideTooltipOnEscapePress, false);

    return (): void => {
      document.removeEventListener('keydown', hideTooltipOnEscapePress, false);
    };
  }, []);

  if (!tooltipOpen) {
    return null;
  }

  return (
    <Paper
      style={{
        left: tooltipLeft,
        position: 'absolute',
        top: tooltipTop,
        width
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
