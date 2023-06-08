import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { FormikValues, useFormikContext } from 'formik';
import { or, equals } from 'ramda';
import { useAtomValue, useSetAtom } from 'jotai';

import { Box } from '@mui/material';
import SaveIcon from '@mui/icons-material/SaveOutlined';

import {
  ConfirmDialog,
  IconButton,
  useMutationQuery,
  Method,
  useSnackbar
} from '@centreon/ui';

import { EditedNotificationIdAtom, panelModeAtom } from '../../atom';
import { isPanelOpenAtom, reloadAtom } from '../../../atom';
import {
  labelSave,
  labelSuccessfulEditNotification,
  labelSuccessfulNotificationAdded,
  labelConfirmAddNotification,
  labelConfirmEditNotification,
  labelDoYouWantToConfirmAction
} from '../../../translatedLabels';
import { notificationtEndpoint } from '../../api/endpoints';
import { adaptNotifications } from '../../api/adapters';
import { PanelMode } from '../../models';

const useStyle = makeStyles()((theme) => ({
  icon: {
    fontSize: theme.spacing(2.5)
  }
}));

const SaveAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();
  const { values, isValid, dirty } = useFormikContext<FormikValues>();

  const [dialogOpen, setDialogOpen] = useState(false);
  const panelMode = useAtomValue(panelModeAtom);
  const editedNotificationId = useAtomValue(EditedNotificationIdAtom);
  const setReload = useSetAtom(reloadAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const { isMutating, mutateAsync } = useMutationQuery({
    getEndpoint: () =>
      equals(panelMode, PanelMode.Create)
        ? notificationtEndpoint({})
        : notificationtEndpoint({ id: editedNotificationId }),
    method: equals(panelMode, PanelMode.Create) ? Method.POST : Method.PUT
  });

  const onClick = (): void => {
    setDialogOpen(true);
  };

  const onCancel = (): void => {
    setDialogOpen(false);
  };

  const onConfirm = (): void => {
    const labelMessage = equals(panelMode, PanelMode.Create)
      ? labelSuccessfulNotificationAdded
      : labelSuccessfulEditNotification;

    mutateAsync(adaptNotifications(values)).then(() => {
      showSuccessMessage(t(labelMessage));
      setDialogOpen(false);
      setPanelOpen(false);
      setReload(true);
    });
  };

  const disabled = or(!isValid, !dirty);

  const labelConfirm = equals(panelMode, PanelMode.Create)
    ? labelConfirmAddNotification
    : labelConfirmEditNotification;

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelSave) as string}
        disabled={disabled as boolean}
        title={t(labelSave) as string}
        onClick={onClick}
      >
        <SaveIcon
          className={classes.icon}
          color={disabled ? 'disabled' : 'primary'}
        />
      </IconButton>
      <ConfirmDialog
        confirmDisabled={isMutating}
        labelMessage={t(labelConfirm)}
        labelTitle={t(labelDoYouWantToConfirmAction)}
        open={dialogOpen}
        submitting={isMutating}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default SaveAction;
