import React, { useState } from 'react';
import PropTypes from 'prop-types';

import ErrorSnackbar from '.';

const withErrorSnackbar = (Component) => {
  const ComponentWithErrorSnackbar = ({ onError, ...rest }) => {
    const [errorMessage, setErrorMessage] = useState();

    const confirmError = () => {
      setErrorMessage(undefined);
    };

    const showError = (message) => {
      setErrorMessage(message);
    };

    const hasError = errorMessage !== undefined;

    return (
      <>
        <Component onError={showError} {...rest} />
        <ErrorSnackbar
          onClose={confirmError}
          open={hasError}
          message={errorMessage}
        />
      </>
    );
  };

  ComponentWithErrorSnackbar.propTypes = {
    onError: PropTypes.func.isRequired,
  };

  return ComponentWithErrorSnackbar;
};

export default withErrorSnackbar;
