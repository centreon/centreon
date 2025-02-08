import { ComponentColumnProps } from '@centreon/ui';
import TooltipContent from './Tooltip/TooltipContent';

import { Tooltip } from '@mui/material';
import { useHostsStyles } from './HostsCount.styles';

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

    const hostCount = enabled
      ? row.enabled_hosts_count
      : row.disabled_hosts_count;

    const formattedHostCount = renderEllipsisTypography?.({
      className: classes.hostCount,
      formattedString: hostCount
    });

    return (
      <Tooltip
        classes={{
          tooltip: classes.tooltipContainer
        }}
        title={<TooltipContent enabled={enabled} hostGroupName={row.name} />}
        arrow
      >
        <div className={classes.content}>{formattedHostCount}</div>
      </Tooltip>
    );
  };

export default Hosts;
