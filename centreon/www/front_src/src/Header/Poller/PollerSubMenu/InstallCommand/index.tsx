import { useTranslation } from 'react-i18next';

import FileCopyIcon from '@mui/icons-material/FileCopy';
import { ListItemButton, ListItemText, Typography } from '@mui/material';

import { useFetchQuery, useCopyToClipboard } from '@centreon/ui';

import { installCommandEndpoint } from '../../../api/endpoints';
import {
  labelFailureCopyPollerCommand,
  labelInstallCommand,
  labelSuccessfulCopyPollerCommand
} from '../../translatedLabels';

import { useStyles } from './InstallCommand.styles';
import { installCommandDecoder } from './decoder';

const InstallCommand = (): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const successMessage = t(labelSuccessfulCopyPollerCommand);
  const errorMessage = t(labelFailureCopyPollerCommand);

  const { copy } = useCopyToClipboard({ errorMessage, successMessage });

  const { data, error } = useFetchQuery({
    decoder: installCommandDecoder,
    getEndpoint: () => installCommandEndpoint,
    getQueryKey: () => ['installCommandPollers']
  });

  if (!data || error) {
    return null;
  }
  const copyCommandInstall = (): Promise<void> => {
    return copy(data.command);
  };

  return (
    <ListItemButton
      disableGutters
      className={classes.button}
      data-testid="clipboard"
      onClick={copyCommandInstall}
    >
      <FileCopyIcon data-testid="clipboardIcon" fontSize="small" />
      <ListItemText
        primary={
          <Typography className={classes.text} variant="body2">
            {t(labelInstallCommand)}
          </Typography>
        }
      />
    </ListItemButton>
  );
};

export default InstallCommand;
