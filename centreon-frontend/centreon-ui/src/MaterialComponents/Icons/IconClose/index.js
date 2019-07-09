/* eslint-disable react/jsx-filename-extension */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable react/prop-types */

import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Close from '@material-ui/icons/Close';

const useStyles = makeStyles(() => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#424242',
    cursor: 'pointer',
    float: 'right',
    fontSize: 32,
    zIndex: 9,
    position: 'absolute',
    right: 11,
    top: 8,
  },
  iconLabel: {
    color: '#009fdf',
    fontSize: 12,
    display: 'inline-block',
    verticalAlign: 'middle',
    fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
    fontWeight: 'bold',
    cursor: 'pointer',
    paddingLeft: 5,
  },
  iconCustomStyle: {
    content: '""',
    position: 'absolute',
    right: 0,
    top: 0,
    width: 54,
    height: 49,
    cursor: 'pointer',
    '&:hover': {
      background: '#707070',
      '& svg': {
        color: '#fff',
      },
    },
  },
}));

function IconClose({ label, customStyle, onClick }) {
  const classes = useStyles();

  return (
    <span onClick={onClick} className={classes.iconCustomStyle}>
      <Close style={customStyle} className={classes.icon} />
      {label && <span className={classes.iconLabel}>{label}</span>}
    </span>
  );
}

export default IconClose;
