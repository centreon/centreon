import React from 'react';

import PropTypes from 'prop-types';
import Snackbar from '@material-ui/core/Snackbar';
import SnackbarContent from '@material-ui/core/SnackbarContent';
import IconButton from '@material-ui/core/IconButton';

import ErrorIcon from '@material-ui/icons/Error';
import CloseIcon from '@material-ui/icons/Close';
import { makeStyles } from '@material-ui/core/styles';

const useStyles = makeStyles((theme) => ({
  error: {
    backgroundColor: theme.palette.error.dark,
    marginRight: theme.spacing(1),
  },
  icon: {
    fontSize: 20,
    opacity: 0.9,
  },
  message: {
    display: 'flex',
    alignItems: 'center',
  },
}));

const ErrorSnackbar = ({ message, open, onClose }) => {
  const classes = useStyles();

  const Message = (
    <span className={classes.message}>
      <ErrorIcon className={`${classes.icon} ${classes.error}`} />
      {message}
    </span>
  );

  return (
    <Snackbar
      anchorOrigin={{
        vertical: 'top',
        horizontal: 'center',
      }}
      open={open}
      autoHideDuration={6000}
      onClose={onClose}
    >
      <SnackbarContent
        className={classes.error}
        message={Message}
        action={[
          <IconButton key="close" color="inherit" onClick={onClose}>
            <CloseIcon className={classes.icon} />
          </IconButton>,
        ]}
      />
    </Snackbar>
  );
};

ErrorSnackbar.defaultProps = {
  onClose: () => {},
};

ErrorSnackbar.propTypes = {
  message: PropTypes.string.isRequired,
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
};

export default ErrorSnackbar;
