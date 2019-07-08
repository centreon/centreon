/* eslint-disable react/jsx-filename-extension */

import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import InsertChart from '@material-ui/icons/InsertChart';

const useStyles = makeStyles(() => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#707070',
    cursor: 'pointer',
  },
  iconWrap: {
    display: 'inline-block',
    verticalAlign: 'middle',
    height: 24,
  },
}));

function IconInsertChart({ ...rest }) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <InsertChart className={classes.icon} />
    </span>
  );
}

export default IconInsertChart;
