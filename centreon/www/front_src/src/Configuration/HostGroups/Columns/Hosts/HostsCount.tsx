import { Tooltip } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { ReactElement } from 'react';

import { useHostsStyles } from './HostsCount.styles';
import TooltipContent from './TooltipContent';

interface Props {
  enabled: boolean;
}

const HostsComponent = ({
  enabled,
  row,
  renderEllipsisTypography,
  isHovered
}: Props & ComponentColumnProps): ReactElement => {
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

const Hosts =
  ({ enabled }: Props) =>
  (props: ComponentColumnProps) => (
    <HostsComponent enabled={enabled} {...props} />
  );

export default Hosts;
