/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import LibraryAdd from '@material-ui/icons/LibraryAdd';

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

function IconLibraryAdd({ customStyle, ...rest }) {
  const classes = useStyles();

  return (
    <span {...rest} className={classes.iconWrap}>
      <LibraryAdd style={customStyle} className={classes.icon} />
    </span>
  );
}

export default IconLibraryAdd;
