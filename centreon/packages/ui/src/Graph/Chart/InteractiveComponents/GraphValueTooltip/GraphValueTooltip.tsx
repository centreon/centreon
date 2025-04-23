import { equals } from 'ramda';

import { Typography } from '@mui/material';

import { Tooltip as MuiTooltip } from '../../../../components/Tooltip';
import { useTooltipStyles } from '../../../common/useTooltipStyles';
import { ThresholdTooltip, Tooltip } from '../../models';

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
        classes={{
          tooltip: classes.tooltip
        }}
        placement="top-start"
        title={<Typography>{thresholdTooltip?.thresholdLabel}</Typography>}
      >
        {children}
      </MuiTooltip>
    );
  }

  return (
    <MuiTooltip
      classes={{
        tooltip: cx(classes.tooltip, classes.tooltipDisablePadding)
      }}
      placement="top-start"
      title={
        equals('hidden', tooltip?.mode) ? null : (
          <GraphValueTooltipContent
            base={baseAxis}
            isSingleMode={equals('single', tooltip?.mode)}
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
