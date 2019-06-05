import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import LibraryAdd from '@material-ui/icons/LibraryAdd';

const useStyles = makeStyles(theme => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#009fdf',
    cursor: 'pointer'
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
}));

function IconLibraryAdd() {
  const classes = useStyles();

  return (
    <div className={classes.root}>
      <LibraryAdd className={classes.icon} />
      <span className={classes.iconLabel}>Duplicate</span>
    </div>
  );
}

export default IconLibraryAdd;
