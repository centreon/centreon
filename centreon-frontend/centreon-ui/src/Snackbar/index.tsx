import React from 'react';

import {
  Snackbar,
  SnackbarContent,
  IconButton,
  makeStyles,
  Theme,
} from '@material-ui/core';
import { Error, CheckCircle, Warning, Info, Close } from '@material-ui/icons';
import { CreateCSSProperties } from '@material-ui/styles';

import Severity from './Severity';

interface PropsStyle {
  getColor: (theme) => string;
}

const useStyles = makeStyles<Theme, PropsStyle>((theme) => ({
  iconColor: ({ getColor }: PropsStyle): CreateCSSProperties<PropsStyle> => ({
    backgroundColor: getColor(theme),
    marginRight: theme.spacing(1),
  }),
  icon: {
    fontSize: 20,
    opacity: 0.9,
  },
  message: {
    display: 'flex',
    alignItems: 'center',
  },
}));

interface Props {
  message: string;
  open: boolean;
  onClose?: () => void;
  severity?: Severity;
}

const snackbarIcons = {
  error: {
    Icon: (props): JSX.Element => <Error {...props} />,
    getColor: (theme): string => theme.palette.error.dark,
  },
  warning: {
    Icon: (props): JSX.Element => <Warning {...props} />,
    getColor: (theme): string => theme.palette.warning.dark,
  },
  success: {
    Icon: (props): JSX.Element => <CheckCircle {...props} />,
    getColor: (theme): string => theme.palette.success.dark,
  },
  info: {
    Icon: (props): JSX.Element => <Info {...props} />,
    getColor: (theme): string => theme.palette.info.dark,
  },
};

const ErrorSnackbar = ({
  message,
  open,
  onClose,
  severity = Severity.success,
}: Props): JSX.Element => {
  const { Icon, getColor } = snackbarIcons[severity];
  const classes = useStyles({ getColor });

  const classNames = `${classes.icon} ${classes.iconColor}`;

  const Message = (
    <span className={classes.message}>
      <Icon className={classNames} />
      {message}
    </span>
  );

  return (
    <Snackbar
      anchorOrigin={{
        vertical: 'bottom',
        horizontal: 'center',
      }}
      open={open}
      autoHideDuration={6000}
      onExited={onClose}
    >
      <SnackbarContent
        className={classes.iconColor}
        message={Message}
        action={[
          <IconButton key="close" color="inherit" onClick={onClose}>
            <Close className={classes.icon} />
          </IconButton>,
        ]}
      />
    </Snackbar>
  );
};

export default ErrorSnackbar;
