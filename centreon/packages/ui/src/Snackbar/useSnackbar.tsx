import { useSnackbar as useNotistackSnackbar } from 'notistack';

import { Typography } from '@mui/material';

import Severity from './Severity';

import Snackbar from '.';

interface ShowMessageProps {
  message: string | JSX.Element;
  severity: Severity;
}

interface ShowMessagesProps {
  messages: Record<string, string>;
  severity: Severity;
}

type ShowMessage = (message: string) => void;
type ShowMessages = (message: Record<string, string>) => void;

interface UseSnackbar {
  showErrorMessage: ShowMessage;
  showErrorMessages: ShowMessages;
  showInfoMessage: ShowMessage;
  showInfoMessages: ShowMessages;
  showSuccessMessage: ShowMessage;
  showSuccessMessages: ShowMessages;
  showWarningMessage: ShowMessage;
  showWarningMessages: ShowMessages;
}

const useSnackbar = (): UseSnackbar => {
  const notistackHookProps = useNotistackSnackbar();

  const snackbarContent =
    (severity) =>
    (key: string | number, message): JSX.Element => {
      return <Snackbar id={key} message={message} severity={severity} />;
    };

  const showMessage = ({ message, severity }: ShowMessageProps): void => {
    notistackHookProps?.enqueueSnackbar(message, {
      content: snackbarContent(severity),
      variant: severity
    });
  };

  const showMessages = ({ messages, severity }: ShowMessagesProps): void => {
    const messageKeys = Object.keys(messages);

    const formattedMessages = messageKeys.map(
      (messageKey) => `${messageKey}: ${messages[messageKey]}`,
      []
    );

    showMessage({
      message: (
        <div>
          {formattedMessages.map((errorMessage, index) => (
            <Typography key={messageKeys[index]} variant="body2">
              {errorMessage}
            </Typography>
          ))}
        </div>
      ),
      severity
    });
  };

  const showSuccessMessage = (message: string): void => {
    showMessage({ message, severity: Severity.success });
  };

  const showSuccessMessages = (messages: Record<string, string>): void => {
    showMessages({ messages, severity: Severity.success });
  };

  const showErrorMessage = (message: string): void => {
    showMessage({ message, severity: Severity.error });
  };

  const showErrorMessages = (messages: Record<string, string>): void => {
    showMessages({ messages, severity: Severity.error });
  };

  const showWarningMessage = (message: string): void => {
    showMessage({ message, severity: Severity.warning });
  };

  const showWarningMessages = (messages: Record<string, string>): void => {
    showMessages({ messages, severity: Severity.warning });
  };

  const showInfoMessage = (message: string): void => {
    showMessage({ message, severity: Severity.info });
  };

  const showInfoMessages = (messages: Record<string, string>): void => {
    showMessages({ messages, severity: Severity.info });
  };

  return {
    showErrorMessage,
    showErrorMessages,
    showInfoMessage,
    showInfoMessages,
    showSuccessMessage,
    showSuccessMessages,
    showWarningMessage,
    showWarningMessages
  };
};

export default useSnackbar;
