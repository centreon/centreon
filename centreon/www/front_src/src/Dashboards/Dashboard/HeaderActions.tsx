import { useEffect, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';

import EditIcon from '@mui/icons-material/Edit';
import { Typography } from '@mui/material';

import { Modal, Button } from '@centreon/ui/components';
import { SaveButton } from '@centreon/ui';

import {
  labelCancel,
  labelCancelDashboard,
  labelEdit,
  labelSave,
  labelSaving,
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
import useSaveDashboard from './useSaveDashboard';

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

  const { saveDashboard, isSaving } = useSaveDashboard();

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

  const saveAndProceed = (): void => {
    saveDashboard();

    if (blocked) {
      proceedNavigation?.();
    }
  };

  useEffect(() => {
    if (!blocked) {
      return;
    }

    setIsAskingCancelConfirmation(true);
  }, [blocked]);

  if (!isEditing) {
    return (
      <Button
        data-testid="edit_dashboard"
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
        data-testid="cancel_dashboard"
        disabled={isSaving}
        variant="ghost"
        onClick={askCancelConfirmation}
      >
        {t(labelCancel)}
      </Button>
      <SaveButton
        labelLoading={t(labelSaving) as string}
        labelSave={t(labelSave) as string}
        loading={isSaving}
        onClick={saveDashboard}
      />
      <Modal
        open={isAskingCancelConfirmation}
        onClose={closeAskCancelConfirmationAndBlock}
      >
        <Modal.Header>{t(labelCancelDashboard)}</Modal.Header>
        <Modal.Body>
          <Typography>{t(labelYouWillCancelPageWithoutSaving)}</Typography>
        </Modal.Body>
        <Modal.Actions
          isLoading={isSaving}
          labels={{
            cancel: labelCancel,
            confirm: labelSave,
            loading: labelSaving
          }}
          onCancel={cancelEditing}
          onConfirm={saveAndProceed}
        />
      </Modal>
    </>
  );
};

export default HeaderActions;
