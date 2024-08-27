import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { ConfirmDialog } from '@centreon/ui';

import {
  isCloseModalConfirmationDialogOpenAtom,
  modalStateAtom
} from '../atom';
import { ModalMode } from '../models';
import {
  labelDoYouWantToQuitWithoutSaving,
  labelYourFormHasUnsavedChanges
} from '../translatedLabels';

const CloseModalConfirmationDialog = (): React.JSX.Element => {
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useAtom(
    isCloseModalConfirmationDialogOpenAtom
  );
  const setModalState = useSetAtom(modalStateAtom);

  const onCancel = (): void => setIsDialogOpen(false);

  const onConfirm = (): void => {
    setIsDialogOpen(false);
    setModalState({ isOpen: false, mode: ModalMode.Create });
  };

  return (
    <ConfirmDialog
      labelMessage={t(labelDoYouWantToQuitWithoutSaving)}
      labelTitle={t(labelYourFormHasUnsavedChanges)}
      open={isDialogOpen}
      onCancel={onCancel}
      onConfirm={onConfirm}
    />
  );
};

export default CloseModalConfirmationDialog;
