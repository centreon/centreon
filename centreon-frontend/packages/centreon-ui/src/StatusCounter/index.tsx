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
    color: theme.palette.common[getColor(theme.palette.mode)],
    cursor: 'pointer',
    fontSize: theme.typography.caption.fontSize,
    height: theme.spacing(1.875),
    minWidth: theme.spacing(1.875),
    padding: theme.spacing(0, 0.5),
  },
}));

export interface Props {
  count: number | JSX.Element;
  severityCode: SeverityCode;
}

const StatusCounter = ({ severityCode, count }: Props): JSX.Element => {
  const { classes } = useStyles({ severityCode });

  return (
    <Badge
      badgeContent={numeral(count).format('0a')}
      classes={{
        badge: classes.badge,
      }}
      overlap="circular"
    />
  );
};

export default StatusCounter;
