import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';

import EditIcon from '@mui/icons-material/Edit';
import { DialogActions, DialogContent, Typography } from '@mui/material';

import { Button, DialogTitle, SimpleDialog } from '@centreon/ui';

import {
  labelCancel,
  labelCancelDashboard,
  labelEdit,
  labelSave,
  labelYouWillCancelPageWithoutSaving
} from './translatedLabels';
import { isEditingAtom, switchPanelsEditionModeDerivedAtom } from './atoms';

const HeaderActions = (): JSX.Element => {
  const { t } = useTranslation();

  const [isAskingCancelConfirmation, setIsAskingCancelConfirmation] =
    useState(false);

  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );

  const startEditing = (): void => {
    switchPanelsEditionMode(true);
  };

  const askCancelConfirmation = (): void => {
    setIsAskingCancelConfirmation(true);
  };

  const closeAskCancelConfirmation = (): void => {
    setIsAskingCancelConfirmation(false);
  };

  const cancelEditing = (): void => {
    switchPanelsEditionMode(false);
  };

  const savePanels = (): void => undefined;

  if (!isEditing) {
    return (
      <Button icon={<EditIcon />} iconVariant="start" onClick={startEditing}>
        {t(labelEdit)}
      </Button>
    );
  }

  return (
    <>
      <Button variant="ghost" onClick={askCancelConfirmation}>
        {t(labelCancel)}
      </Button>
      <SimpleDialog
        open={isAskingCancelConfirmation}
        onClose={closeAskCancelConfirmation}
      >
        <DialogTitle>{t(labelCancelDashboard)}</DialogTitle>
        <DialogContent>
          <Typography>{t(labelYouWillCancelPageWithoutSaving)}</Typography>
        </DialogContent>
        <DialogActions>
          <Button variant="ghost" onClick={cancelEditing}>
            {t(labelCancel)}
          </Button>
          <Button onClick={savePanels}>{t(labelSave)}</Button>
        </DialogActions>
      </SimpleDialog>
    </>
  );
};

export default HeaderActions;
