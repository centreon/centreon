/* eslint-disable react/prop-types */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable no-dupe-keys */

import React from 'react';
import Button from '@material-ui/core/Button';
import { makeStyles } from '@material-ui/core/styles';
import AddIcon from '@material-ui/icons/Add';

const useStyles = makeStyles((theme) => ({
  button: {
    margin: theme.spacing(1),
    display: 'flex',
    fontSize: 12,
    margin: 0,
    padding: '7px 10px',
  },
  leftIcon: {
    marginRight: theme.spacing(1),
    width: '0.8em',
    height: '0.8em',
  },
}));

const ButtonAdd = ({ label, onClick, ...rest }) => {
  const classes = useStyles();

  return (
    <Button
      variant="contained"
      color="primary"
      className={classes.button}
      onClick={onClick}
      {...rest}
    >
      <AddIcon className={classes.leftIcon} iconsize="small" />
      {label}
    </Button>
  );
};

export default ButtonAdd;
