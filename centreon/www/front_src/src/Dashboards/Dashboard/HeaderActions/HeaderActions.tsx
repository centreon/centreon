import { ReactElement, useCallback, useEffect, useMemo, useState } from 'react';

import { useTranslation } from 'react-i18next';
import { useAtomValue, useSetAtom } from 'jotai';
import { useSearchParams } from 'react-router-dom';

import EditOutlinedIcon from '@mui/icons-material/EditOutlined';
import ShareIcon from '@mui/icons-material/Share';
import { Typography } from '@mui/material';

import { Button, IconButton, Modal } from '@centreon/ui/components';

import { Dashboard, DashboardPanel } from '../../api/models';
import { formatPanel } from '../useDashboardDetails';
import useDashboardDirty from '../useDashboardDirty';
import { selectedDashboardShareAtom } from '../../Shares/atoms';
import { Shares } from '../../Shares';
import { labelShareTheDashboard } from '../../translatedLabels';
import useSaveDashboard from '../useSaveDashboard';
import {
  dashboardAtom,
  isEditingAtom,
  switchPanelsEditionModeDerivedAtom
} from '../atoms';
import {
  labelEditDashboard,
  labelExit,
  labelExitDashboard,
  labelExitEditionMode,
  labelLeaveEditionModeChangesNotSaved,
  labelQuitDashboardChangesNotSaved,
  labelSave
} from '../translatedLabels';

interface HeaderActionsProps {
  id?: Dashboard['id'];
  name?: string;
  panels?: Array<DashboardPanel>;
}

/* eslint-disable @typescript-eslint/no-unused-vars */
const HeaderActions = ({
  id,
  name,
  panels
}: HeaderActionsProps): ReactElement => {
  const { t } = useTranslation();

  const [isAskingCancelConfirmation, setIsAskingCancelConfirmation] =
    useState(false);

  const isEditing = useAtomValue(isEditingAtom);
  const switchPanelsEditionMode = useSetAtom(
    switchPanelsEditionModeDerivedAtom
  );
  const setDashboard = useSetAtom(dashboardAtom);
  const setSelectedDashboardShareAtom = useSetAtom(selectedDashboardShareAtom);

  /**
   * TODO useDashboardSaveBlocker issue with default router behaviour
   * re-enable when fixed and widget edition is implemented
   */
  // const { blocked, blockNavigation, proceedNavigation } =
  //   useDashboardSaveBlocker({ id, name });
  const blocked = false;
  // eslint-disable-next-line @typescript-eslint/explicit-function-return-type,@typescript-eslint/no-empty-function
  const blockNavigation = () => {};
  // eslint-disable-next-line @typescript-eslint/explicit-function-return-type,@typescript-eslint/no-empty-function
  const proceedNavigation = () => {};

  const { saveDashboard } = useSaveDashboard();

  const dirty = useDashboardDirty(
    (panels || []).map((panel) => formatPanel({ panel, staticPanel: false }))
  );

  const [searchParams, setSearchParams] = useSearchParams();

  const startEditing = useCallback(() => {
    switchPanelsEditionMode(true);
    if (searchParams.get('edit') !== 'true') {
      searchParams.set('edit', 'true');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  const stopEditing = useCallback(() => {
    switchPanelsEditionMode(false);
    if (searchParams.get('edit') !== null) {
      searchParams.delete('edit');
      setSearchParams(searchParams);
    }
  }, [searchParams, setSearchParams]);

  useEffect(() => {
    if (searchParams.get('edit') === 'true') startEditing();
    if (searchParams.get('edit') === null) stopEditing();
  }, [searchParams]);

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

  const openShareModal = (): void => setSelectedDashboardShareAtom(id);

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

  // TODO evaluate if we need styling here (the PageHeader component is already taking care of this)
  if (!isEditing) {
    return (
      <>
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
        <IconButton
          aria-label={t(labelShareTheDashboard) as string}
          data-testid={labelShareTheDashboard}
          icon={<ShareIcon />}
          onClick={openShareModal}
        />
        <Shares id={id} />
      </>
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
        size="small"
        variant="ghost"
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

export { HeaderActions };
