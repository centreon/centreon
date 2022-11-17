import * as React from 'react';

import { SnackbarProvider } from 'notistack';

import Transition from './Transition';

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
    return (
      <SnackbarProvider
        TransitionComponent={Transition}
        anchorOrigin={{ horizontal: 'center', vertical: 'bottom' }}
        maxSnack={maxSnackbars}
      >
        <Component {...props} />
      </SnackbarProvider>
    );
  };
};

export default withSnackbar;
