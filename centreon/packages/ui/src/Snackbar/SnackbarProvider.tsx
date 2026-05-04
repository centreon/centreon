import type { TransitionProps } from '@mui/material/transitions';

import { SnackbarProvider as NotistackSnackbarProvider } from 'notistack';
import type { JSXElementConstructor, ReactElement } from 'react';

import Transition from './Transition';

interface Props {
  children: ReactElement;
  maxSnackbars?: number;
}

const SnackbarProvider = ({
  children,
  maxSnackbars = 1
}: Props): JSX.Element => {
  return (
    <NotistackSnackbarProvider
      anchorOrigin={{ horizontal: 'center', vertical: 'bottom' }}
      maxSnack={maxSnackbars}
      TransitionComponent={
        Transition as JSXElementConstructor<
          TransitionProps & {
            children;
          }
        >
      }
    >
      {children}
    </NotistackSnackbarProvider>
  );
};

export default SnackbarProvider;
