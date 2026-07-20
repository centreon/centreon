import CopyIcon from '@mui/icons-material/FileCopyOutlined';
import { Box, Typography } from '@mui/material';

import { IconButton, useCopyToClipboard } from '@centreon/ui';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelCommand,
  labelCommandCopied,
  labelCopyCommand,
  labelFailedToCopyTheCommand
} from '../../../translatedLabels';

interface Props {
  commandLine: string;
  defaultMessage?: string;
}

export const CommandLine = ({
  commandLine,
  defaultMessage = ''
}: Props): ReactElement => {
  const { t } = useTranslation();

  const { copy } = useCopyToClipboard({
    errorMessage: t(labelFailedToCopyTheCommand),
    successMessage: t(labelCommandCopied)
  });

  if (!commandLine) {
    return (
      <Box className="bg-action-disabled-background text-action-primary rounded-sm p-2">
        <Typography data-testid={labelCommand}>
          {defaultMessage && t(defaultMessage)}
        </Typography>
      </Box>
    );
  }

  return (
    <Box className="bg-text-primary text-primary-contrastText rounded-sm p-2 flex justify-between">
      <Typography data-testid={labelCommand}>{commandLine}</Typography>
      <IconButton
        ariaLabel={t(labelCopyCommand)}
        className="text-primary-contrastText"
        onClick={() => copy(commandLine)}
        title={t(labelCopyCommand)}
      >
        <CopyIcon />
      </IconButton>
    </Box>
  );
};
