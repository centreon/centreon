import React from 'react';

import { useSnackbar, SnackbarContent } from 'notistack';
import { isNil, not } from 'ramda';

import { IconButton, makeStyles } from '@material-ui/core';
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

export interface SnackbarProps {
  id: string | number;
  message: string;
  severity: Severity;
}

const Snackbar = React.forwardRef(
  (
    { message, id, severity }: SnackbarProps,
    ref: React.ForwardedRef<HTMLDivElement>,
  ): JSX.Element => {
    const classes = useStyles();
    const { closeSnackbar } = useSnackbar();
    const timeoutId = React.useRef<NodeJS.Timeout | number | undefined>();

    React.useEffect((): void => {
      timeoutId.current = setTimeout(() => {
        closeSnackbar(id);
      }, 6000);
    }, []);

    const close = (): void => {
      if (not(isNil(timeoutId.current))) {
        clearTimeout(timeoutId.current as number);
      }
      closeSnackbar(id);
    };

    return (
      <SnackbarContent ref={ref}>
        <Alert
          action={[
            <IconButton color="inherit" key="close" onClick={close}>
              <IconClose className={classes.closeIcon} />
            </IconButton>,
          ]}
          classes={{ icon: classes.alertIcon, message: classes.message }}
          severity={severity}
          variant="filled"
        >
          {message}
        </Alert>
      </SnackbarContent>
    );
  },
);

export default Snackbar;
