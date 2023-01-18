import * as React from 'react';

import { SnackbarProvider as NotistackSnackbarProvider } from 'notistack';

import Transition from './Transition';
import { TransitionProps } from '@mui/material/transitions';

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
      TransitionComponent={Transition as React.JSXElementConstructor<TransitionProps & {
        children;
    }>}
      anchorOrigin={{ horizontal: 'center', vertical: 'bottom' }}
      maxSnack={maxSnackbars}
    >
      {children}
    </NotistackSnackbarProvider>
  );
};

export default SnackbarProvider;
