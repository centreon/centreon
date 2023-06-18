import { useCallback, useEffect, useMemo, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';

import EditOutlinedIcon from '@mui/icons-material/EditOutlined';
import { Typography } from '@mui/material';

import { Button, Modal } from '@centreon/ui/components';

import {
  labelEditDashboard,
  labelExit,
  labelExitDashboard,
  labelExitEditionMode,
  labelLeaveEditionModeChangesNotSaved,
  labelQuitDashboardChangesNotSaved,
  labelSave
} from './translatedLabels';
import {
  dashboardAtom,
  isEditingAtom,
  switchPanelsEditionModeDerivedAtom
} from './atoms';
import { PanelDetails } from './models';
import { formatPanel } from './useDashboardDetails';
import useSaveDashboard from './useSaveDashboard';
import useDashboardDirty from './useDashboardDirty';

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

  // const { blocked, blockNavigation, proceedNavigation } =
  //   useDashboardSaveBlocker({ id, name });

  const blocked = false;
  const blockNavigation = () => {};
  const proceedNavigation = () => {};

  const { saveDashboard } = useSaveDashboard();

  const dirty = useDashboardDirty(
    (panels || []).map((panel) => formatPanel({ panel, staticPanel: false }))
  );

  const [searchParams, setSearchParams] = useSearchParams();

  const startEditing = useCallback(() => {
    switchPanelsEditionMode(true);
    if (searchParams.get('view') !== 'edit') {
      searchParams.set('view', 'edit');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  const stopEditing = useCallback(() => {
    switchPanelsEditionMode(false);
    if (searchParams.get('view') !== 'default') {
      searchParams.set('view', 'default');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  useEffect(() => {
    if (searchParams.get('view') === 'edit') startEditing();
    if (searchParams.get('view') === 'default') stopEditing();
  }, []);

  const askCancelConfirmation = (): void => {
    if (!dirty) {
      stopEditing();

      return;
    }
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
    stopEditing();
    closeAskCancelConfirmationAndProceed();
  };

  const saveAndProceed = (): void => {
    saveDashboard();
    setIsAskingCancelConfirmation(false);
    switchPanelsEditionMode(false);

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

  const modalTitle = useMemo(
    () =>
      blocked && isAskingCancelConfirmation
        ? t(labelExitDashboard, { dashboardName: name })
        : t(labelExitEditionMode),
    [blocked, isAskingCancelConfirmation, name]
  );
  const modalMessage = useMemo(
    () =>
      blocked && isAskingCancelConfirmation
        ? t(labelQuitDashboardChangesNotSaved, { dashboardName: name })
        : t(labelLeaveEditionModeChangesNotSaved),
    [blocked, isAskingCancelConfirmation, name]
  );

  if (!isEditing) {
    return (
      <Button
        data-testid="edit_dashboard"
        icon={<EditOutlinedIcon />}
        iconVariant="start"
        size="small"
        variant="ghost"
        onClick={startEditing}
      >
        {t(labelEditDashboard)}
      </Button>
    );
  }

  return (
    <>
      <Button
        aria-label={t(labelExit) as string}
        data-testid="cancel_dashboard"
        size="small"
        variant="ghost"
        onClick={askCancelConfirmation}
      >
        {t(labelExit)}
      </Button>
      <Button
        aria-label={t(labelSave) as string}
        data-testid="save_dashboard"
        disabled={!dirty}
        onClick={saveAndProceed}
      >
        {t(labelSave)}
      </Button>
      <Modal
        open={isAskingCancelConfirmation}
        onClose={closeAskCancelConfirmationAndBlock}
      >
        <Modal.Header>{modalTitle}</Modal.Header>
        <Modal.Body>
          <Typography>{modalMessage}</Typography>
        </Modal.Body>
        <Modal.Actions
          labels={{
            cancel: t(labelExit),
            confirm: t(labelSave)
          }}
          onCancel={cancelEditing}
          onConfirm={saveAndProceed}
        />
      </Modal>
    </>
  );
};

export default HeaderActions;
