import numeral from 'numeral';
import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { Badge } from '@mui/material';

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
    transform: 'none'
  }
}));

export interface Props {
  className?: string;
  count: number | JSX.Element;
  severityCode: SeverityCode;
}

const StatusCounter = ({
  severityCode,
  count,
  className
}: Props): JSX.Element => {
  const { classes, cx } = useStyles({ severityCode });

  return (
    <Badge
      badgeContent={numeral(count).format('0a')}
      classes={{
        badge: cx(classes.badge, className)
      }}
      overlap="circular"
    />
  );
};

export default StatusCounter;
