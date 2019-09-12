import { useContext } from 'react';
import { ErrorSnackbarContext } from './withErrorSnackbar';

const useErrorSnackbar = () => {
  return useContext(ErrorSnackbarContext);
};

export default useErrorSnackbar;
