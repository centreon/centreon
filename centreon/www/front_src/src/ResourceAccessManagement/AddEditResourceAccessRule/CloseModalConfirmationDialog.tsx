import { useTranslation } from 'react-i18next';
import { useAtom, useSetAtom } from 'jotai';

import { ConfirmDialog } from '@centreon/ui';

import {
  labelDoYouWantToQuitWithoutSaving,
  labelYourFormHasUnsavedChanges
} from '../translatedLabels';
import {
  editedResourceAccessRuleIdAtom,
  isCloseModalConfirmationDialogOpenAtom,
  modalStateAtom
} from '../atom';

const CloseModalConfirmationDialog = (): React.JSX.Element => {
  const { t } = useTranslation();

  const [isDialogOpen, setIsDialogOpen] = useAtom(
    isCloseModalConfirmationDialogOpenAtom
  );
  const [modalState, setModalState] = useAtom(modalStateAtom);
  const setEditedRuleId = useSetAtom(editedResourceAccessRuleIdAtom);

  const onCancel = (): void => setIsDialogOpen(false);

  const onConfirm = (): void => {
    setIsDialogOpen(false);
    setModalState({ ...modalState, isOpen: false });
    setEditedRuleId(null);
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
