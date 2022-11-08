import * as React from 'react';

import { not } from 'ramda';

import { Badge, makeStyles, Typography } from '@material-ui/core';

const useStyles = makeStyles((theme) => ({
  badge: {
    backgroundColor: '#29d1d3',
  },
  iconName: {
    color: theme.palette.background.paper,
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
        classes={{ badge: classes.badge }}
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
