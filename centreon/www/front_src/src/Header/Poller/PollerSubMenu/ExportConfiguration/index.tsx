import { Button, Typography } from '@mui/material';

import { Dialog, getData, useRequest, useSnackbar } from '@centreon/ui';

import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { exportAndReloadConfigurationEndpoint } from '../../../api/endpoints';
import {
  labelCancel,
  labelConfigurationExportedAndReloaded,
  labelExportAndReload,
  labelExportAndReloadTheConfiguration,
  labelExportConfiguration,
  labelExportingAndReloadingTheConfiguration,
  labelFailedToExportAndReloadConfiguration,
  labelThisWillExportAndReloadOnAllOfYourPlatform
} from '../../translatedLabels';

interface Props {
  closeSubMenu: () => void;
}

const ExportConfiguration = ({ closeSubMenu }: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const [askingBeforeExportConfiguration, setAskingBeforeExportConfiguration] =
    useState(false);
  const { sendRequest, sending } = useRequest({
    defaultFailureMessage: t(labelFailedToExportAndReloadConfiguration),
    request: getData
  });
  const { showInfoMessage, showSuccessMessage } = useSnackbar();

  const askBeforeExportConfiguration = (): void => {
    setAskingBeforeExportConfiguration(true);
  };

  const closeConfirmDialog = (): void =>
    setAskingBeforeExportConfiguration(false);

  const confirmExportAndReload = (): void => {
    closeSubMenu();
    showInfoMessage(t(labelExportingAndReloadingTheConfiguration));
    sendRequest({
      endpoint: exportAndReloadConfigurationEndpoint
    }).then(() => {
      showSuccessMessage(t(labelConfigurationExportedAndReloaded));
    });
    closeConfirmDialog();
  };

  const disableButton = sending;

  return (
    <>
      <Button
        data-testid={labelExportConfiguration}
        disabled={disableButton}
        fullWidth
        onClick={askBeforeExportConfiguration}
        size="small"
        variant="outlined"
      >
        {t(labelExportConfiguration)}
      </Button>
      <Dialog
        labelCancel={t(labelCancel) as string}
        labelConfirm={t(labelExportAndReload) as string}
        labelTitle={t(labelExportAndReloadTheConfiguration) as string}
        onCancel={closeConfirmDialog}
        onClose={closeConfirmDialog}
        onConfirm={confirmExportAndReload}
        open={askingBeforeExportConfiguration}
      >
        <div>
          <Typography>
            {t(labelThisWillExportAndReloadOnAllOfYourPlatform)}
          </Typography>
        </div>
      </Dialog>
    </>
  );
};

export default ExportConfiguration;
