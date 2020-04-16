import React from 'react';

import {
  Snackbar as MuiSnackbar,
  IconButton,
  makeStyles,
} from '@material-ui/core';
import IconClose from '@material-ui/icons/Close';
import { Alert } from '@material-ui/lab';

import Severity from './Severity';

interface PropsStyle {
  getColor: (theme) => string;
}

const useStyles = makeStyles<PropsStyle>({
  closeIcon: {
    fontSize: 20,
    opacity: 0.9,
  },
  alertIcon: {
    paddingTop: '10px',
  },
  message: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
  },
});

interface Props {
  message: string;
  open: boolean;
  onClose?: () => void;
  severity: Severity;
}

const Snackbar = ({ message, open, onClose, severity }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <MuiSnackbar
      anchorOrigin={{
        vertical: 'bottom',
        horizontal: 'center',
      }}
      open={open}
      autoHideDuration={6000}
      onClose={onClose}
    >
      <Alert
        variant="filled"
        severity={severity}
        action={[
          <IconButton key="close" color="inherit" onClick={onClose}>
            <IconClose className={classes.closeIcon} />
          </IconButton>,
        ]}
        classes={{ icon: classes.alertIcon, message: classes.message }}
      >
        {message}
      </Alert>
    </MuiSnackbar>
  );
};

export default Snackbar;
