import { equals } from 'ramda';

import { Tooltip as MuiTooltip } from '../../../../components/Tooltip';
import { Tooltip } from '../../models';
import { useTooltipStyles } from '../../../common/useTooltipStyles';

import GraphValueTooltipContent from './GraphValueTooltipContent';

interface Props {
  baseAxis: number;
  children: JSX.Element;
  tooltip?: Tooltip;
}

const GraphValueTooltip = ({
  children,
  tooltip,
  baseAxis
}: Props): JSX.Element => {
  const { classes, cx } = useTooltipStyles();

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
