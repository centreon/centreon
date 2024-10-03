import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { dialogStateAtom, isCloseModalDialogOpenAtom } from '../atoms';
import {
  labelCancel,
  labelConfirm,
  labelDoYouWantToQuitWithoutSaving,
  labelYourFormHasUnsavedChanges
} from '../translatedLabels';

const CloseModalConfirmation = (): React.JSX.Element => {
  const { t } = useTranslation();

  const [isModalOpen, setIsModalOpen] = useAtom(isCloseModalDialogOpenAtom);
  const setDialogState = useSetAtom(dialogStateAtom);

  const onCancel = (): void => setIsModalOpen(false);

  const onConfirm = (): void => {
    setIsModalOpen(false);
    setDialogState((dialogState) => ({ ...dialogState, isOpen: false }));
  };

  return (
    <Modal open={isModalOpen} size="medium" onClose={onCancel}>
      <Modal.Header>{t(labelDoYouWantToQuitWithoutSaving)}</Modal.Header>
      <Modal.Body>
        <Typography>{t(labelYourFormHasUnsavedChanges)}</Typography>
      </Modal.Body>
      <Modal.Actions
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelConfirm)
        }}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Modal>
  );
};

export default CloseModalConfirmation;
