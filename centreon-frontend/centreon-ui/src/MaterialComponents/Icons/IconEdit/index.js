import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Edit from '@material-ui/icons/Edit';

const useStyles = makeStyles((theme) => ({
  root: {
    display: 'flex',
    justifyContent: 'center',
    alignItems: 'center',
  },
  icon: {
    color: '#0072CE',
    cursor: 'pointer',
    fontSize: 21,
    position: 'absolute',
    right: 3,
  },
}));

function IconEdit({ customStyle, onClick }) {
  const classes = useStyles();

  return (
    <Edit onClick={onClick} style={customStyle} className={classes.icon} />
  );
}

export default IconEdit;
