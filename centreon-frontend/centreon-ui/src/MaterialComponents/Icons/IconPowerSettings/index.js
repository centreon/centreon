import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import PowerSettings from '@material-ui/icons/PowerSettingsNew';

const useStyles = makeStyles(theme => ({
  root: {
    display: 'flex',
    alignItems: 'center',
    textAlign: 'left',
  },
  icon: {
    color: '#fff',
    cursor: 'pointer',
    backgroundColor: '#009fdf',
    borderRadius: '50%',
    fontSize: 15,
    padding: 3,
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
  },
  iconNormal: {
    color: '#fff',
    cursor: 'pointer',
    backgroundColor: '#707070',
    borderRadius: '50%',
    fontSize: 15,
    padding: 3,
  },
  iconActive: {
    color: '#fff',
    cursor: 'pointer',
    backgroundColor: '#89b72b',
    borderRadius: '50%',
    fontSize: 15,
    padding: 3,
  },
  iconWrap: {
    display: 'inline-block',
    verticalAlign: 'middle',
  }
}));

function IconPowerSettings({label, active, customStyle, ...rest}) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <PowerSettings style={customStyle} className={active ? classes.iconActive : classes.iconNormal} />
      {label && <span className={classes.iconLabel}>{label}</span>}
    </span>
  );
}

export default IconPowerSettings;
