import React from 'react';
import clsx from 'clsx';
import Button from '@material-ui/core/Button';
import { makeStyles } from '@material-ui/core/styles';
import AddIcon from '@material-ui/icons/Add';

const useStyles = makeStyles(theme => ({
  button: {
    display: 'flex',
    backgroundColor: '#1174cb',
    color: '#fff',
    fontSize: 12,
    padding: '5px 10px',
    '&:hover': {
      backgroundColor: '#1e68a9'
    }
  },
  leftIcon: {
    marginRight: theme.spacing(1),
  },
}));

function ButtonCusom({label, onClick}) {
  const classes = useStyles();

  return (
    <Button onClick={onClick} variant="contained" color="secondary" className={classes.button}>
      <AddIcon className={classes.leftIcon} iconsize="small" />
      {label}
    </Button>
  );
}

export default ButtonCusom;
