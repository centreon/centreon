import * as React from 'react';

import { SnackbarProvider } from 'notistack';

import Transition from './Transition';

import Snackbar, { SnackbarProps } from '.';

interface SnackbarContextProviderProps {
  children?: React.ReactNode;
}

interface WithSnackbarProps {
  Component: (props) => JSX.Element;
  maxSnackbars?: number;
}

const withSnackbar = ({
  Component,
  maxSnackbars = 3,
}: WithSnackbarProps): ((props) => JSX.Element) => {
  return (props: SnackbarContextProviderProps): React.ReactElement => {
    const snackbarContent = (
      id: string | number,
      { message, severity }: Omit<SnackbarProps, 'id'>,
    ): JSX.Element => {
      return <Snackbar id={id} message={message} severity={severity} />;
    };

    return (
      <SnackbarProvider
        TransitionComponent={Transition}
        anchorOrigin={{ horizontal: 'center', vertical: 'bottom' }}
        content={snackbarContent}
        maxSnack={maxSnackbars}
      >
        <Component {...props} />
      </SnackbarProvider>
    );
  };
};

export default withSnackbar;
