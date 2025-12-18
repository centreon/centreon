import { Tooltip } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { useHostsStyles } from './HostsCount.styles';
import TooltipContent from './TooltipContent';

interface Props {
  enabled: boolean;
}

const Hosts =
  ({ enabled }: Props) =>
  ({
    row,
    renderEllipsisTypography,
    isHovered
  }: ComponentColumnProps): JSX.Element => {
    const { classes } = useHostsStyles({ isHovered });

    const hostCount = enabled ? row.enabledHostsCount : row.disabledHostsCount;

    const formattedHostCount = renderEllipsisTypography?.({
      className: classes.hostCount,
      formattedString: hostCount
    });

    return (
      <Tooltip
        arrow
        classes={{
          tooltip: classes.tooltipContainer
        }}
        title={<TooltipContent enabled={enabled} hostGroupName={row.name} />}
      >
        <div className={classes.content}>{formattedHostCount}</div>
      </Tooltip>
    );
  };

export default Hosts;
