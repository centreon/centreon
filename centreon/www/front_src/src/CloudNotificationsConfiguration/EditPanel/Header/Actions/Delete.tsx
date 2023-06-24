import { useAtomValue, useSetAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';
import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import {
  labelDeleteNotification,
  labelFailedToDeleteNotification,
  labelNotificationSuccessfullyDeleted
} from '../../../translatedLabels';
import { editedNotificationIdAtom } from '../../atom';
import { notificationEndpoint } from '../../api/endpoints';
import { ConfirmationDialog, useDelete, DeleteButton } from '../../../Actions';
import { isPanelOpenAtom } from '../../../atom';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.5)
  }
}));

const DeleteAction = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyle();

  const notificationId = useAtomValue(editedNotificationIdAtom);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const getEndpoint = (): string =>
    notificationEndpoint({ id: notificationId });

  const {
    values: { name }
  } = useFormikContext<FormikValues>();

  const { onClick, dialogOpen, isMutating, onCancel, onConfirm } = useDelete({
    getEndpoint,
    labelFailed: labelFailedToDeleteNotification,
    labelSuccess: labelNotificationSuccessfullyDeleted,
    onSuccess: () => setPanelOpen(false)
  });

  return (
    <Box>
      <DeleteButton
        ariaLabel={t(labelDeleteNotification) as string}
        iconClassName={classes.icon}
        onClick={onClick}
      />
      <ConfirmationDialog
        dialogOpen={dialogOpen}
        isMutating={isMutating}
        notificationName={name}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DeleteAction;
