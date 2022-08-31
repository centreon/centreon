import { not } from 'ramda';

import { Badge, Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

const useStyles = makeStyles((theme) => ({
  icon: {
    color: theme.palette.common.white,
    cursor: 'pointer',
    [theme.breakpoints.down(768)]: {
      height: theme.spacing(5),
      minWidth: theme.spacing(4.5),
    },
  },
  iconName: {
    color: theme.palette.common.white,
    display: 'block',
    fontSize: theme.typography.body2.fontSize,
    lineHeight: '1',
    whiteSpace: 'nowrap',
    [theme.breakpoints.down(768)]: {
      display: 'none',
    },
  },
  iconWrap: {
    alignItems: 'center',
    cursor: 'pointer',
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'space-between',
  },
}));

interface Props {
  Icon: (props) => JSX.Element;
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
        color="pending"
        invisible={not(pending)}
        overlap="circular"
        variant="dot"
      >
        <Icon className={classes.icon} onClick={onClick} />
      </Badge>
      <Typography
        className={classes.iconName}
        variant="caption"
        onClick={onClick}
      >
        {iconName}
      </Typography>
    </span>
  );
};

export default IconHeader;
