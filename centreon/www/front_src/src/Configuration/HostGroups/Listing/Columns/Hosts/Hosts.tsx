import { ComponentColumnProps } from '@centreon/ui';
import TooltipContent from './Tooltip/TooltipContent';
import { useTooltipStyles } from './useTooltipStyles';

import { Tooltip } from '@mui/material';

const Hosts =
  ({ enabled }: { enabled: boolean }) =>
  ({ row }: ComponentColumnProps): JSX.Element => {
    const hostCount = enabled
      ? row.enabled_hosts_count
      : row.disabled_hosts_count;

    const { classes } = useTooltipStyles();

    return (
      <Tooltip
        classes={{
          tooltip: classes.tooltip
        }}
        title={<TooltipContent enabled={enabled} hostGroupName={row.name} />}
        arrow
      >
        <div className={classes.content}>{hostCount} 11</div>
      </Tooltip>
    );
  };

export default Hosts;
