import numeral from 'numeral';
import { makeStyles } from 'tss-react/mui';

import { Badge } from '@mui/material';

import { getStatusColors, SeverityCode } from '../StatusChip';

export interface StyleProps {
  severityCode: SeverityCode;
}

const useStyles = makeStyles<StyleProps>()((theme, { severityCode }) => ({
  badge: {
    background: getStatusColors({ severityCode, theme }).backgroundColor,
    color: getStatusColors({ severityCode, theme }).color,
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
