import numeral from 'numeral';

import { Theme } from '@mui/material';
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
    statusCounter: ({ severityCode }): CreateCSSProperties<StyleProps> => ({
      alignItems: 'center',
      background: getStatusIconColors(severityCode).backgroundColor,
      borderRadius: '50%',
      color: getStatusIconColors(severityCode).color,
      cursor: 'pointer',
      display: 'inline-flex',
      fontSize: theme.typography.caption.fontSize,
      justifyContent: 'center',
      minHeight: theme.spacing(2),
      minWidth: theme.spacing(2),
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
    <div className={classes.statusCounter}>{numeral(count).format('0a')}</div>
  );
};

export default StatusCounter;
