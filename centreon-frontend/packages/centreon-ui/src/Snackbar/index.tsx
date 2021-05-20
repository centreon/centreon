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
  alertIcon: {
    paddingTop: '10px',
  },
  closeIcon: {
    fontSize: 20,
    opacity: 0.9,
  },
  message: {
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
  },
});

interface Props {
  message: string;
  onClose?: () => void;
  open: boolean;
  severity: Severity;
}

const Snackbar = ({ message, open, onClose, severity }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <MuiSnackbar
      anchorOrigin={{
        horizontal: 'center',
        vertical: 'bottom',
      }}
      autoHideDuration={6000}
      open={open}
      onClose={onClose}
    >
      <Alert
        action={[
          <IconButton color="inherit" key="close" onClick={onClose}>
            <IconClose className={classes.closeIcon} />
          </IconButton>,
        ]}
        classes={{ icon: classes.alertIcon, message: classes.message }}
        severity={severity}
        variant="filled"
      >
        {message}
      </Alert>
    </MuiSnackbar>
  );
};

export default Snackbar;
