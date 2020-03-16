import React, { useState } from 'react';

import { SnackbarActions } from './withSnackbar';
import Severity from './Severity';

interface SnackbarContent {
  message;
  severity;
  confirmMessage: () => void;
}

const useMessage = (): SnackbarContent & SnackbarActions => {
  const [snackbarMessage, setSnackbarMessage] = useState();
  const [snackbarSeverity, setSnackbarSeverity] = useState(Severity.error);

  const confirmMessage = (): void => {
    setSnackbarMessage(undefined);
  };

  const showMessage = ({ message, severity }): void => {
    setSnackbarMessage(message);
    setSnackbarSeverity(severity);
  };

  const showMessages = ({ messages, severity }): void => {
    const messageKeys = Object.keys(messages);

    const formattedMessages = messageKeys.map(
      (messageKey) => `${messageKey}: ${messages[messageKey]}`,
      [],
    );

    showMessage({
      message: (
        <div style={{ display: 'block' }}>
          {formattedMessages.map((errorMessage, index) => (
            <p style={{ margin: 0 }} key={messageKeys[index]}>
              {errorMessage}
            </p>
          ))}
        </div>
      ),
      severity,
    });
  };

  return {
    message: snackbarMessage,
    severity: snackbarSeverity,
    confirmMessage,
    showMessage,
    showMessages,
  };
};

export default useMessage;
