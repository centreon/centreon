import * as React from 'react';

import { SnackbarProvider as NotistackSnackbarProvider } from 'notistack';

import Transition from './Transition';

interface Props {
  children: React.ReactElement;
  maxSnackbars?: number;
}

const SnackbarProvider = ({
  children,
  maxSnackbars = 1
}: Props): JSX.Element => {
  return (
    <NotistackSnackbarProvider
      TransitionComponent={Transition}
      anchorOrigin={{ horizontal: 'center', vertical: 'bottom' }}
      maxSnack={maxSnackbars}
    >
      {children}
    </NotistackSnackbarProvider>
  );
};

export default SnackbarProvider;
