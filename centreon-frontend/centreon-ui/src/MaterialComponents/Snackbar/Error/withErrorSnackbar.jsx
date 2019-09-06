import React, { createContext, useState } from 'react';

import ErrorSnackbar from '.';

const Context = createContext({});

const withErrorSnackbar = (Component) => {
  return (props) => {
    const [errorMessage, setErrorMessage] = useState();

    const confirmError = () => {
      setErrorMessage(undefined);
    };

    const showError = (message) => {
      setErrorMessage(message);
    };

    const hasError = errorMessage !== undefined;

    return (
      <Context.Provider value={{ showError }}>
        <Component {...props} />
        <ErrorSnackbar
          onClose={confirmError}
          open={hasError}
          message={errorMessage}
        />
      </Context.Provider>
    );
  };
};

export default withErrorSnackbar;
export { Context as ErrorSnackbarContext };
