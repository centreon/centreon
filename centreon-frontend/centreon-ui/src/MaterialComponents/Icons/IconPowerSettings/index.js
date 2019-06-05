import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import PowerSettings from '@material-ui/icons/PowerSettingsNew';

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
    marginLeft: 5,
  }
}));

function IconPowerSettings() {
  const classes = useStyles();

  return (
    <div className={classes.root}>
      <PowerSettings className={classes.icon} />
      <span className={classes.iconLabel}>Enable/Disable</span>
    </div>
  );
}

export default IconPowerSettings;
