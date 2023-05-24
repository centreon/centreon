import { useEffect, useState } from 'react';

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
import {
  dashboardAtom,
  isEditingAtom,
  switchPanelsEditionModeDerivedAtom
} from './atoms';
import useDashboardSaveBlocker from './useDashboardSaveBlocker';
import { PanelDetails } from './models';
import { formatPanel } from './useDashboardDetails';

interface HeaderActionsProps {
  id?: number;
  name?: string;
  panels?: Array<PanelDetails>;
}

const HeaderActions = ({
  id,
  name,
  panels
}: HeaderActionsProps): JSX.Element => {
  const { t } = useTranslation();

  const [isAskingCancelConfirmation, setIsAskingCancelConfirmation] =
    useState(false);

  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );
  const setDashboard = useSetAtom(dashboardAtom);

  const { blocked, blockNavigation, proceedNavigation } =
    useDashboardSaveBlocker({ id, name });

  const startEditing = (): void => {
    switchPanelsEditionMode(true);
  };

  const askCancelConfirmation = (): void => {
    setIsAskingCancelConfirmation(true);
  };

  const closeAskCancelConfirmationAndBlock = (): void => {
    setIsAskingCancelConfirmation(false);

    if (blocked) {
      blockNavigation?.();
    }
  };

  const closeAskCancelConfirmationAndProceed = (): void => {
    setIsAskingCancelConfirmation(false);

    if (blocked) {
      proceedNavigation?.();
    }
  };

  const cancelEditing = (): void => {
    setDashboard({
      layout: panels?.map((panel) => formatPanel({ panel })) || []
    });
    switchPanelsEditionMode(false);
    closeAskCancelConfirmationAndProceed();
  };

  const savePanels = (): void => undefined;

  useEffect(() => {
    if (!blocked) {
      return;
    }

    setIsAskingCancelConfirmation(true);
  }, [blocked]);

  if (!isEditing) {
    return (
      <Button
        dataTestId="edit_dashboard"
        icon={<EditIcon />}
        iconVariant="start"
        onClick={startEditing}
      >
        {t(labelEdit)}
      </Button>
    );
  }

  return (
    <>
      <Button
        dataTestId="cancel_dashboard"
        variant="ghost"
        onClick={askCancelConfirmation}
      >
        {t(labelCancel)}
      </Button>
      <SimpleDialog
        open={isAskingCancelConfirmation}
        onClose={closeAskCancelConfirmationAndBlock}
      >
        <DialogTitle>{t(labelCancelDashboard)}</DialogTitle>
        <DialogContent>
          <Typography>{t(labelYouWillCancelPageWithoutSaving)}</Typography>
        </DialogContent>
        <DialogActions>
          <Button
            dataTestId="cancel_dashboard_confirmation"
            variant="ghost"
            onClick={cancelEditing}
          >
            {t(labelCancel)}
          </Button>
          <Button dataTestId="save_dashboard_confirmation" onClick={savePanels}>
            {t(labelSave)}
          </Button>
        </DialogActions>
      </SimpleDialog>
    </>
  );
};

export default HeaderActions;
