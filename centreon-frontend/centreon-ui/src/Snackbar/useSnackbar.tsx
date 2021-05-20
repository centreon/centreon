import { useContext } from 'react';

import { SnackbarContext, SnackbarActions } from './withSnackbar';

const useSnackbar = (): SnackbarActions => {
  return useContext(SnackbarContext);
};

export default useSnackbar;
