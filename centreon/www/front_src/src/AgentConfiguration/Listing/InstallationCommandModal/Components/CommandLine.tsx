import CopyIcon from '@mui/icons-material/FileCopyOutlined';
import { Box, Typography } from '@mui/material';

import { IconButton, useCopyToClipboard } from '@centreon/ui';

import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  labelCommandCopied,
  labelCopyCommand,
  labelFailedToCopyTheCommand
} from '../../../translatedLabels';

export const CommandLine = ({
  commandLine
}: {
  commandLine: string;
}): ReactElement => {
  const { t } = useTranslation();

  const { copy } = useCopyToClipboard({
    errorMessage: t(labelFailedToCopyTheCommand),
    successMessage: t(labelCommandCopied)
  });

  return (
    <Box className="bg-text-primary text-primary-contrastText rounded-sm p-2 flex justify-between">
      <Typography>{commandLine}</Typography>
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
