import { useEffect } from 'react';

import { Paper } from '@mui/material';

import useGraphTooltip from './useGraphTooltip';

const tooltipWidth = 165;
interface Props {
  graphWidth: number;
}

const GraphTooltip = ({ graphWidth }: Props): JSX.Element | null => {
  const { hideTooltip, tooltipLeft, tooltipOpen, tooltipTop } = useGraphTooltip(
    { graphWidth, tooltipWidth }
  );

  const hideTooltipOnEspcapePress = (event: globalThis.KeyboardEvent): void => {
    if (event.key === 'Escape') {
      hideTooltip();
    }
  };

  useEffect(() => {
    document.addEventListener('keydown', hideTooltipOnEspcapePress, false);

    return (): void => {
      document.removeEventListener('keydown', hideTooltipOnEspcapePress, false);
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
        width: tooltipWidth
      }}
    >
      hola soy el tooltip
    </Paper>
  );
};

export default GraphTooltip;
