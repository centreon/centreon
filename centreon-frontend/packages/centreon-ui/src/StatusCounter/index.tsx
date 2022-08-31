import numeral from 'numeral';

import { Badge, Theme } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import { CreateCSSProperties } from '@mui/styles';

import { getStatusColors } from '..';
import { Colors, SeverityCode } from '../StatusChip';

export interface StyleProps {
  severityCode: SeverityCode;
}

const useStyles = makeStyles<Theme, StyleProps>((theme) => {
  const getStatusIconColors = (severityCode: SeverityCode): Colors =>
    getStatusColors({
      severityCode,
      theme,
    });

  return {
    badge: ({ severityCode }): CreateCSSProperties<StyleProps> => ({
      background: getStatusIconColors(severityCode).backgroundColor,
      color: getStatusIconColors(severityCode).color,
      cursor: 'pointer',
      fontSize: theme.typography.caption.fontSize,
      height: theme.spacing(1.875),
      minWidth: theme.spacing(1.875),
      padding: theme.spacing(0, 0.5),
    }),
  };
});

export interface Props {
  count: number | JSX.Element;
  severityCode: SeverityCode;
}

const StatusCounter = ({ severityCode, count }: Props): JSX.Element => {
  const classes = useStyles({ severityCode });

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
