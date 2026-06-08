import { Badge, Tooltip, alpha } from '@mui/material';

import { getStatusColors, type SeverityCode } from '@centreon/ui';

import numeral from 'numeral';
import { makeStyles } from 'tss-react/mui';

export interface StyleProps {
  severityCode?: SeverityCode | null;
}

const useStyles = makeStyles<StyleProps>()((theme, { severityCode }) => {
  const statusColor = severityCode
    ? getStatusColors({ severityCode, theme })?.backgroundColor
    : null;

  return {
    badge: {
      background: statusColor ? alpha(statusColor, 0.8) : 'transparent',
      borderRadius: theme.spacing(1.5),
      color: theme.palette.common.white,
      cursor: 'pointer',
      fontSize: theme.typography.body1.fontSize,
      fontWeight: theme.typography.fontWeightBold,
      height: theme.spacing(2.5),
      lineHeight: theme.spacing(2.5),
      minWidth: theme.spacing(2.5),
      padding: theme.spacing(0, 0.75),
      position: 'relative',
      right: 0,
      top: 0,
      transform: 'none'
    }
  };
});

export interface Props {
  className?: string;
  count: string | number;
  severityCode?: SeverityCode | null;
}

const StatusCounter = ({
  severityCode = null,
  count,
  className
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ severityCode });
  const shouldDisableTooltip = Number(count) < 1000;
  const formattedCount = numeral(count).format('0.[0]a');

  return (
    <Tooltip
      disableHoverListener={shouldDisableTooltip}
      followCursor
      title={count}
    >
      <Badge
        badgeContent={formattedCount}
        classes={{
          badge: cx(classes.badge, className)
        }}
        max={Number.POSITIVE_INFINITY}
        overlap="circular"
      />
    </Tooltip>
  );
};

export default StatusCounter;
