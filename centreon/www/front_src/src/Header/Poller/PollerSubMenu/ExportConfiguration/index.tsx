import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import { Button, Typography } from '@mui/material';

import { getData, useRequest, useSnackbar, Dialog } from '@centreon/ui';

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
import { exportAndReloadConfigurationEndpoint } from '../../../api/endpoints';

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
        fullWidth
        data-testid={labelExportConfiguration}
        disabled={disableButton}
        size="small"
        variant="outlined"
        onClick={askBeforeExportConfiguration}
      >
        {t(labelExportConfiguration)}
      </Button>
      <Dialog
        labelCancel={t(labelCancel) as string}
        labelConfirm={t(labelExportAndReload) as string}
        labelTitle={t(labelExportAndReloadTheConfiguration) as string}
        open={askingBeforeExportConfiguration}
        onCancel={closeConfirmDialog}
        onClose={closeConfirmDialog}
        onConfirm={confirmExportAndReload}
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
