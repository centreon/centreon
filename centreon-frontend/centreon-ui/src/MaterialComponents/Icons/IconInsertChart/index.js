import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import InsertChart from '@material-ui/icons/InsertChart';

const useStyles = makeStyles(theme => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#009fdf',
    cursor: 'pointer',
  },
  iconLabel: {
    color: '#009fdf',
    fontSize: 12, 
    display: 'inline-block',
    verticalAlign: 'super',
    fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
    fontWeight: 'bold',
    cursor: 'pointer',
    paddingLeft: 5,
  },
  iconWrap: {
    display: 'inline-block',
    verticalAlign: 'middle',
  }
}));

function IconInsertChart({label,...rest}) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <InsertChart className={classes.icon} />
      {label && <span className={classes.iconLabel}>{label}</span>}
    </span>
  );
}

export default IconInsertChart;
