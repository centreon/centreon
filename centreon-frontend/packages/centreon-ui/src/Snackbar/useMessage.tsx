import React, { useState } from 'react';

import { SnackbarActions } from './withSnackbar';
import Severity from './Severity';

interface SnackbarContent {
  confirmMessage: () => void;
  message;
  severity;
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
            <p key={messageKeys[index]} style={{ margin: 0 }}>
              {errorMessage}
            </p>
          ))}
        </div>
      ),
      severity,
    });
  };

  return {
    confirmMessage,
    message: snackbarMessage,
    severity: snackbarSeverity,
    showMessage,
    showMessages,
  };
};

export default useMessage;
