import { not } from 'ramda';

import { Badge, Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles((theme) => ({
  iconName: {
    color: theme.palette.common.white,
    display: 'block',
    fontSize: '.6875rem',
    textTransform: 'lowercase',
  },
  iconWrap: {
    alignItems: 'center',
    cursor: 'pointer',
    display: 'flex',
    flexDirection: 'column',
    marginRight: '11px',
  },
}));

interface Props {
  Icon: (props) => JSX.Element | null;
  iconName: string;
  onClick?: () => void;
  pending?: boolean;
}

const IconHeader = ({
  Icon,
  iconName,
  onClick,
  pending,
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <span className={classes.iconWrap}>
      <Badge
        anchorOrigin={{ horizontal: 'right', vertical: 'bottom' }}
        color="info"
        invisible={not(pending)}
        overlap="circular"
        variant="dot"
      >
        <Icon
          style={{ color: '#FFFFFF', cursor: 'pointer' }}
          onClick={onClick}
        />
      </Badge>
      <span className={classes.iconName}>
        <Typography variant="caption">{iconName}</Typography>
      </span>
    </span>
  );
};

export default IconHeader;
