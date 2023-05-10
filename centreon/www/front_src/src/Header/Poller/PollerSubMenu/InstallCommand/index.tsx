import { RefObject, useRef } from 'react';

import { useTranslation } from 'react-i18next';

import { ListItemButton, ListItemText } from '@mui/material';
import FileCopyIcon from '@mui/icons-material/FileCopy';

import { useFetchQuery, useSnackbar } from '@centreon/ui';

import {
  labelInstallCommand,
  labelSuccessfulCopyPollerCommand,
  labelFailureCopyPollerCommand
} from '../../translatedLabels';
import { installCommandEndpoint } from '../../../api/endpoints';

import { useStyles } from './InstallCommand.styles';
import { installCommandDecoder } from './decoder';

const InstallCommand = (): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const { data, error } = useFetchQuery({
    decoder: installCommandDecoder,
    getEndpoint: () => installCommandEndpoint,
    getQueryKey: () => ['installCommandPollers']
  });

  const installCommandRef = useRef<HTMLTextAreaElement>();

  const onCopy = (): void => {
    if (!installCommandRef || !installCommandRef?.current) {
      return;
    }

    const value = installCommandRef.current?.value;
    navigator.clipboard.writeText(value).then(
      () => {
        showSuccessMessage(t(labelSuccessfulCopyPollerCommand));
      },
      () => {
        showErrorMessage(t(labelFailureCopyPollerCommand));
      }
    );
  };

  if (!data || error) {
    return null;
  }

  return (
    <>
      <ListItemButton onClick={onCopy}>
        <FileCopyIcon component="div" fontSize="small" />
        <ListItemText>{t(labelInstallCommand)}</ListItemText>
      </ListItemButton>
      <textarea
        readOnly
        className={classes.hidden}
        ref={installCommandRef as RefObject<HTMLTextAreaElement>}
        value={data.command}
      />
    </>
  );
};

export default InstallCommand;
