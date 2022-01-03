import React from 'react';

import { Typography } from '@mui/material';

import useSnackbar from './useSnackbar';
import withSnackbar from './withSnackbar';

export default { title: 'Snackbar' };

interface Props {
  displayMessages?: boolean;
}

const Story = ({ displayMessages = false }: Props): JSX.Element => {
  const {
    showErrorMessage,
    showErrorMessages,
    showInfoMessage,
    showInfoMessages,
    showSuccessMessage,
    showSuccessMessages,
    showWarningMessage,
    showWarningMessages,
  } = useSnackbar();

  const message = 'This is a message';

  const messages = {
    first: 'my first message',
    second: 'my second message',
  };

  const snackbars = [
    {
      showSnackbar: displayMessages ? showSuccessMessages : showSuccessMessage,
    },
    {
      showSnackbar: displayMessages ? showErrorMessages : showErrorMessage,
    },
    {
      showSnackbar: displayMessages ? showWarningMessages : showWarningMessage,
    },
    {
      showSnackbar: displayMessages ? showInfoMessages : showInfoMessage,
    },
  ];

  React.useEffect(() => {
    snackbars.forEach(({ showSnackbar }) => {
      showSnackbar(
        (displayMessages ? messages : message) as string &
          Record<string, string>,
      );
    });
  }, [displayMessages]);

  return <Typography>Snackbars</Typography>;
};

const StoryWithSnackbar = withSnackbar({ Component: Story, maxSnackbars: 4 });

export const snackbar = (): JSX.Element => <StoryWithSnackbar />;

export const snackbarWithMessages = (): JSX.Element => (
  <StoryWithSnackbar displayMessages />
);
