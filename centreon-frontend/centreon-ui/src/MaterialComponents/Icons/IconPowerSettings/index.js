/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import PowerSettings from '@material-ui/icons/PowerSettingsNew';

const useStyles = makeStyles(() => ({
  root: {
    display: 'flex',
    alignItems: 'center',
    textAlign: 'left',
  },
  icon: {
    color: '#fff',
    cursor: 'pointer',
    backgroundColor: '#707070',
    borderRadius: '50%',
    fontSize: 15,
    padding: 3,
  },
  iconNormal: {
    color: '#fff',
    cursor: 'pointer',
    backgroundColor: '#707070',
    borderRadius: '50%',
    fontSize: 15,
    padding: 3,
  },
  iconWrap: {
    display: 'inline-block',
    verticalAlign: 'middle',
    height: 23,
    width: 23,
  },
}));

function IconPowerSettings({ active, customStyle, ...rest }) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <PowerSettings style={customStyle} className={classes.iconNormal} />
    </span>
  );
}

export default IconPowerSettings;
