import React, { createContext, ReactNode, ReactElement } from 'react';

import useMessage from './useMessage';

import Snackbar from '.';

export interface SnackbarActions {
  showMessage: ({ message, severity }) => void;
  showMessages: ({ messages, severity }) => void;
}

const noOp = (): void => undefined;

const defaultSnackBarState: SnackbarActions = {
  showMessage: noOp,
  showMessages: noOp,
};

const Context = createContext<SnackbarActions>(defaultSnackBarState);

interface SnackbarContextProviderProps {
  children?: ReactNode;
}

const withSnackbar = (
  Component: (props) => JSX.Element,
): ((props) => JSX.Element) => {
  return (props: SnackbarContextProviderProps): ReactElement => {
    const {
      message,
      severity,
      showMessage,
      showMessages,
      confirmMessage,
    } = useMessage();

    const hasMessage = message !== undefined;

    return (
      <Context.Provider value={{ showMessage, showMessages }}>
        <Component {...props} />
        <Snackbar
          message={message}
          open={hasMessage}
          severity={severity}
          onClose={confirmMessage}
        />
      </Context.Provider>
    );
  };
};

export { Context as SnackbarContext };

export default withSnackbar;
