import numeral from 'numeral';
import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { Badge, Tooltip } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import { getStatusColors, SeverityCode } from '../StatusChip';

export interface StyleProps {
  severityCode: SeverityCode;
}

const getColor = (themeMode: string): string =>
  equals(ThemeMode.dark, themeMode) ? 'white' : 'dark';

const useStyles = makeStyles<StyleProps>()((theme, { severityCode }) => ({
  badge: {
    background: getStatusColors({ severityCode, theme }).backgroundColor,
    borderRadius: theme.spacing(1.25),
    color: theme.palette.common[getColor(theme.palette.mode)],
    cursor: 'pointer',
    fontSize: theme.typography.body2.fontSize,
    height: theme.spacing(2.5),
    lineHeight: theme.spacing(2.5),
    minWidth: theme.spacing(2.5),
    position: 'relative',
    right: 0,
    top: 0,
    transform: 'none',
  },
}));

export interface Props {
  className?: string;
  count: string | number;
  severityCode: SeverityCode;
}

const StatusCounter = ({
  severityCode,
  count,
  className,
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ severityCode });
  const shouldFormatCount = Number(count) >= 1000;
  const formattedCount = shouldFormatCount
    ? numeral(count).format('0.0a')
    : count.toString();

  return shouldFormatCount ? (
    <Tooltip followCursor title={count}>
      <Badge
        badgeContent={formattedCount}
        classes={{
          badge: cx(classes.badge, className),
        }}
        max={Infinity}
        overlap="circular"
      />
    </Tooltip>
  ) : (
    <Badge
      badgeContent={formattedCount}
      classes={{
        badge: cx(classes.badge, className),
      }}
      max={Infinity}
      overlap="circular"
    />
  );
};

export default StatusCounter;
