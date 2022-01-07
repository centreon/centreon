import * as React from 'react';

import { SnackbarProvider as NotistackSnackbarProvider } from 'notistack';

import Transition from './Transition';

import Snackbar, { SnackbarProps } from '.';

interface Props {
  children: React.ReactElement;
  maxSnackbars?: number;
}

const SnackbarProvider = ({
  children,
  maxSnackbars = 1,
}: Props): JSX.Element => {
  const snackbarContent = (
    id: string | number,
    { message, severity }: Omit<SnackbarProps, 'id'>,
  ): JSX.Element => {
    return <Snackbar id={id} message={message} severity={severity} />;
  };

  return (
    <NotistackSnackbarProvider
      TransitionComponent={Transition}
      anchorOrigin={{ horizontal: 'center', vertical: 'bottom' }}
      content={snackbarContent}
      maxSnack={maxSnackbars}
    >
      {children}
    </NotistackSnackbarProvider>
  );
};

export default SnackbarProvider;
