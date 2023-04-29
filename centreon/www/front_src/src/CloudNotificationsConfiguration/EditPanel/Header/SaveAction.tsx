import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { FormikValues, useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useAtom, useSetAtom } from 'jotai';

import { Box } from '@mui/material';
import SaveIcon from '@mui/icons-material/SaveOutlined';

import {
  ConfirmDialog,
  IconButton,
  useMutationQuery,
  Method
} from '@centreon/ui';

import { panelModeAtom } from '../atom';
import { isPanelOpenAtom } from '../../atom';
import DeleteDialog from '../../Listing/Dialogs/DeleteDialog';
import { labelSave } from '../../translatedLabels';
import { notificationtEndpoint } from '../api/endpoints';
import { PanelMode } from '../models';
import { adaptNotification } from '../api/adapters';

const useStyle = makeStyles()((theme) => ({
  icon: {
    fontSize: theme.spacing(2.75)
  }
}));

const SaveAction = (): JSX.Element => {
  const { t } = useTranslation();
  const [openSaveDialog, setOpenSaveDialog] = useState(false);
  const [panelMode] = useAtom(panelModeAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const { isError, isMutating, mutate, mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(panelMode, PanelMode.Create)
        ? notificationtEndpoint({})
        : notificationtEndpoint({ id: 1 }),
    method: equals(panelMode, PanelMode.Create) ? Method.POST : Method.PUT
    // decoder,
    // defaultFailureMessage,
    // fetchHeaders ,
    // httpCodesBypassErrorSnackbar,
  });

  const { classes } = useStyle();

  const { values } = useFormikContext<FormikValues>();

  const onSaveActionClick = (): void => {
    setOpenSaveDialog(true);
  };

  const onSaveActionCancel = (): void => {
    setOpenSaveDialog(false);
  };

  const onSaveActionConfirm = (): void => {
    // console.log('adapter :', adaptNotification(values));
    mutateAsync(adaptNotification(values)).then(() => {
      setOpenSaveDialog(false);
      setPanelOpen(false);
    });
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelSave)}
        disabled={false}
        title={t(labelSave)}
        onClick={onSaveActionClick}
      >
        <SaveIcon className={classes.icon} color="primary" />
      </IconButton>
      <ConfirmDialog
        open={openSaveDialog}
        onCancel={onSaveActionCancel}
        onConfirm={onSaveActionConfirm}
      />
    </Box>
  );
};

export default SaveAction;
