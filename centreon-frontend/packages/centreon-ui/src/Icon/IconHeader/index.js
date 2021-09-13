/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';

import clsx from 'clsx';
import { not } from 'ramda';

import { Badge, makeStyles, Typography } from '@material-ui/core';

import styles from './icon-header.scss';

const useStyles = makeStyles({
  badge: {
    backgroundColor: '#29d1d3',
  },
});

const IconHeader = ({ Icon, iconName, style, onClick, children, pending }) => {
  const classes = useStyles();

  return (
    <span className={clsx(styles['icons-wrap'])} style={style}>
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
      <span className={clsx(styles.icons__name)}>
        <Typography variant="caption">{iconName}</Typography>
      </span>
      {children}
    </span>
  );
};

export default IconHeader;
