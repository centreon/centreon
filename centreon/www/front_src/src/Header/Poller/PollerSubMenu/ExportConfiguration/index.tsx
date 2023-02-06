import { useState, useEffect } from "react";

import { useTranslation } from "react-i18next";
import { equals, not } from "ramda";
import { useAtomValue } from "jotai/utils";
import { makeStyles } from "tss-react/mui";
import { baseEndpoint } from "../../../../api/endpoint";
import { Button, Typography } from "@mui/material";

import { getData, useRequest, useSnackbar, Dialog } from "@centreon/ui";
import { ThemeMode } from "@centreon/ui-context";

import {
  labelCancel,
  labelConfigurationExportedAndReloaded,
  labelExportAndReload,
  labelExportAndReloadTheConfiguration,
  labelExportConfiguration,
  labelExportingAndReloadingTheConfiguration,
  labelFailedToExportAndReloadConfiguration,
  labelThisWillExportAndReloadOnAllOfYourPlatform,
} from "../../translatedLabels";

export const exportAndReloadConfigurationEndpoint = `${baseEndpoint}/configuration/monitoring-servers/generate-and-reload`;

interface Props {
  closeSubMenu: () => void;
}

const ExportConfiguration = ({ closeSubMenu }: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const [askingBeforeExportConfiguration, setAskingBeforeExportConfiguration] =
    useState(false);
  const { sendRequest, sending } = useRequest({
    defaultFailureMessage: t(labelFailedToExportAndReloadConfiguration),
    request: getData,
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
      endpoint: exportAndReloadConfigurationEndpoint,
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
        variant="outlined"
        data-testid={labelExportConfiguration}
        disabled={disableButton}
        size="small"
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
