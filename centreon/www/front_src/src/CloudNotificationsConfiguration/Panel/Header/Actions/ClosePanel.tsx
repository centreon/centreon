import { useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import CloseIcon from '@mui/icons-material/Close';

import { ConfirmDialog, IconButton } from '@centreon/ui';

import { isPanelOpenAtom } from '../../../atom';
import {
  labelClosePanel,
  labelDoYouWantToQuitWithoutSaving,
  labelYourFormHasUnsavedChanges
} from '../../../translatedLabels';
import { editedNotificationIdAtom } from '../../atom';

const useStyles = makeStyles()((theme) => ({
  button: {
    paddingLeft: theme.spacing(1.5)
  },
  icon: {
    color: theme.palette.text.primary,
    fontSize: theme.spacing(2.5)
  }
}));

const ClosePanelAction = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [dialogOpen, setIsDialogOpen] = useState(false);
  const setIsPanelOpen = useSetAtom(isPanelOpenAtom);
  const setEditedNotificationId = useSetAtom(editedNotificationIdAtom);

  const { dirty } = useFormikContext<FormikValues>();

  const closePanelEdit = (): void => {
    setIsPanelOpen(false);
    setEditedNotificationId(null);
  };

  const askBeforeClosePanelEdit = (): void => {
    if (dirty) {
      setIsDialogOpen(true);

      return;
    }
    closePanelEdit();
  };

  const onCancel = (): void => setIsDialogOpen(false);

  const onConfirm = (): void => {
    setIsDialogOpen(false);
    setIsPanelOpen(false);
    setEditedNotificationId(null);
  };

  return (
    <>
      <IconButton
        ariaLabel={t(labelClosePanel) as string}
        className={classes.button}
        title={t(labelClosePanel) as string}
        onClick={askBeforeClosePanelEdit}
      >
        <CloseIcon className={classes.icon} />
      </IconButton>
      <ConfirmDialog
        labelMessage={t(labelDoYouWantToQuitWithoutSaving)}
        labelTitle={t(labelYourFormHasUnsavedChanges)}
        open={dialogOpen}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </>
  );
};

export default ClosePanelAction;
