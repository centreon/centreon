import { Dispatch, SetStateAction, useState } from 'react';

import { and, isNil, not } from 'ramda';

interface UnsavedDialogOpened {
  action: () => void;
}

interface PanelSubmitForm {
  submitForm: () => Promise<void>;
}

interface Props {
  isValidForm: boolean;
}

interface UseUnsavedChanges {
  closeUnsavedDialog: () => void;
  discardUnsavedDialog: () => void;
  openUnsavedDialog: (action: () => void) => void;
  panelSubmitForm: PanelSubmitForm | null;
  savePanelChanges: () => void;
  setPanelSubmitForm: Dispatch<SetStateAction<PanelSubmitForm | null>>;
  setUnsavedDialogOpened: Dispatch<SetStateAction<UnsavedDialogOpened | null>>;
  unsavedDialogOpened: UnsavedDialogOpened | null;
}

const useUnsavedChanges = ({ isValidForm }: Props): UseUnsavedChanges => {
  const [unsavedDialogOpened, setUnsavedDialogOpened] =
    useState<UnsavedDialogOpened | null>(null);
  const [panelSubmitForm, setPanelSubmitForm] =
    useState<PanelSubmitForm | null>(null);

  const openUnsavedDialog = (action: () => void): void => {
    setUnsavedDialogOpened({ action });
  };

  const discardUnsavedDialog = (): void => {
    if (isNil(unsavedDialogOpened)) {
      return;
    }
    unsavedDialogOpened.action();
    setUnsavedDialogOpened(null);
  };

  const closeUnsavedDialog = (): void => {
    setUnsavedDialogOpened(null);
  };

  const savePanelChanges = (): void => {
    if (and(isNil(panelSubmitForm), not(isValidForm))) {
      setUnsavedDialogOpened(null);

      return;
    }
    panelSubmitForm?.submitForm().then(() => {
      unsavedDialogOpened?.action();
    });
  };

  return {
    closeUnsavedDialog,
    discardUnsavedDialog,
    openUnsavedDialog,
    panelSubmitForm,
    savePanelChanges,
    setPanelSubmitForm,
    setUnsavedDialogOpened,
    unsavedDialogOpened
  };
};

export default useUnsavedChanges;
