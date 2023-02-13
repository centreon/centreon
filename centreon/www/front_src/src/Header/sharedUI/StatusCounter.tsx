import numeral from 'numeral';
import { makeStyles } from 'tss-react/mui';

import { Badge, Tooltip } from '@mui/material';

import { getStatusColors, SeverityCode } from '@centreon/ui';

export interface StyleProps {
  severityCode?: SeverityCode | null;
}

const useStyles = makeStyles<StyleProps>()((theme, { severityCode }) => ({
  badge: {
    background: severityCode
      ? getStatusColors({ severityCode, theme })?.backgroundColor
      : 'transparent',
    borderRadius: theme.spacing(1.25),
    color: theme.palette.common.black,
    cursor: 'pointer',
    fontSize: theme.typography.body2.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    height: theme.spacing(2.125),
    lineHeight: theme.spacing(2.125),
    minWidth: theme.spacing(2.125),
    position: 'relative',
    right: 0,
    top: 0,
    transform: 'none'
  }
}));

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

  const formattedCount =
    Number(count) >= 1000 ? numeral(count).format('0.0a') : count.toString();

  return Number(count) >= 1000 ? (
    <Tooltip followCursor title={count}>
      <Badge
        badgeContent={formattedCount}
        classes={{
          badge: cx(classes.badge, className)
        }}
        max={Infinity}
        overlap="circular"
      />
    </Tooltip>
  ) : (
    <Badge
      badgeContent={formattedCount}
      classes={{
        badge: cx(classes.badge, className)
      }}
      max={Infinity}
      overlap="circular"
    />
  );
};

export default StatusCounter;
