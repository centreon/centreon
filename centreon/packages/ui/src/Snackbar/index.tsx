/* eslint-disable react/no-danger */
import * as React from 'react';

import { useSnackbar, SnackbarContent } from 'notistack';
import { isNil, not } from 'ramda';
import { makeStyles } from 'tss-react/mui';
import DOMPurify from 'dompurify';

import { IconButton, Alert } from '@mui/material';
import IconClose from '@mui/icons-material/Close';

import Severity from './Severity';

const useStyles = makeStyles()((theme) => ({
  alertIcon: {
    paddingTop: theme.spacing(1.25)
  },
  closeIcon: {
    fontSize: 20,
    opacity: 0.9
  },
  message: {
    '& a': {
      color: theme.palette.primary.main,
      textDecoration: 'none'
    },
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center'
  }
}));

export interface SnackbarProps {
  id: string | number;
  message: string;
  severity: Severity;
}

const Snackbar = React.forwardRef(
  (
    { message, id, severity }: SnackbarProps,
    ref: React.ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes } = useStyles();
    const { closeSnackbar } = useSnackbar();
    const timeoutId = React.useRef<number | undefined>();

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

    const formatedMessage =
      typeof message === 'string' ? (
        <div
          dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(message) }}
        />
      ) : (
        message
      );

    return (
      <SnackbarContent ref={ref}>
        <Alert
          action={[
            <IconButton
              color="inherit"
              key="close"
              size="large"
              onClick={close}
            >
              <IconClose className={classes.closeIcon} />
            </IconButton>
          ]}
          classes={{ icon: classes.alertIcon, message: classes.message }}
          severity={severity}
          variant="filled"
        >
          {formatedMessage}
        </Alert>
      </SnackbarContent>
    );
  }
);

export default Snackbar;
