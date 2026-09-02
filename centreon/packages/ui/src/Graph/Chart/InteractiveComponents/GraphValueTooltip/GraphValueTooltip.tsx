import { Typography } from '@mui/material';

import { equals } from 'ramda';

import { Tooltip as MuiTooltip } from '../../../../components/Tooltip';
import { useTooltipStyles } from '../../../common/useTooltipStyles';
import type { ThresholdTooltip, Tooltip } from '../../models';
import GraphValueTooltipContent from './GraphValueTooltipContent';

interface Props {
  baseAxis: number;
  children: JSX.Element;
  thresholdTooltip: ThresholdTooltip | null;
  tooltip?: Tooltip;
}

const GraphValueTooltip = ({
  children,
  tooltip,
  baseAxis,
  thresholdTooltip
}: Props): JSX.Element => {
  const { classes, cx } = useTooltipStyles();

  if (thresholdTooltip) {
    return (
      <MuiTooltip
        PopperProps={{
          popperOptions: {
            strategy: 'fixed'
          }
        }}
        classes={{
          tooltip: classes.tooltip
        }}
        placement="top-start"
        // @ts-expect-error - suppressing pre-existing type mismatch
        title={<Typography>{thresholdTooltip?.thresholdLabel}</Typography>}
      >
        {children}
      </MuiTooltip>
    );
  }

  return (
    <MuiTooltip
      PopperProps={{
        popperOptions: {
          strategy: 'fixed'
        }
      }}
      classes={{
        tooltip: cx(classes.tooltip, classes.tooltipDisablePadding)
      }}
      placement="top-start"
      // @ts-expect-error - suppressing pre-existing type mismatch
      title={
        equals('hidden', tooltip?.mode) ? null : (
          <GraphValueTooltipContent
            base={baseAxis}
            isSingleMode={equals('single', tooltip?.mode)}
            // @ts-expect-error - suppressing pre-existing type mismatch
            sortOrder={tooltip?.sortOrder}
          />
        )
      }
    >
      {children}
    </MuiTooltip>
  );
};

export default GraphValueTooltip;
