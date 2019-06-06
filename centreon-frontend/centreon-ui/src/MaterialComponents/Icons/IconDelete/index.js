import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Delete from '@material-ui/icons/Delete';

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
  }
}));

function IconDelete({label, customStyle}) {
  const classes = useStyles();

  return (
    <React.Fragment>
      <Delete style={customStyle} className={classes.icon} />
      {label && <span className={classes.iconLabel}>{label}</span>}
    </React.Fragment>
  );
}

export default IconDelete;
