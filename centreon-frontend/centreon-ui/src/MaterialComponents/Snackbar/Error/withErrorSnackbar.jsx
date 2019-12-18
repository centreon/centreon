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

    const showErrors = (errors) => {
      const errorKeys = Object.keys(errors);

      const formattedErrors = errorKeys.reduce(
        (acc, current) => [...acc, `${current}: ${errors[current]}`],
        [],
      );

      showError(
        <div style={{ display: 'block' }}>
          {formattedErrors.map((err, index) => (
            <p style={{ margin: 0 }} key={errorKeys[index]}>
              {err}
            </p>
          ))}
        </div>,
      );
    };

    const hasError = errorMessage !== undefined;

    return (
      <Context.Provider value={{ showError, showErrors }}>
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
