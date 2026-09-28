import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { useAtom, useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  formActionsAtom,
  isResetConfirmationDialogOpenAtom
} from '../../atoms';
import {
  labelCancel,
  labelReset,
  labelResetConfirmation
} from '../../translatedLabels';

const ResetDialog = (): ReactElement => {
  const { t } = useTranslation();

  const [isOpened, setIsOpened] = useAtom(isResetConfirmationDialogOpenAtom);
  const formActions = useAtomValue(formActionsAtom);

  const close = (): void => setIsOpened(false);

  const confirm = (): void => {
    formActions?.reset();
    setIsOpened(false);
  };

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{t(labelReset)}</Modal.Header>
      <Modal.Body>
        <Typography>{t(labelResetConfirmation)}</Typography>
      </Modal.Body>
      <Modal.Actions
        isDanger
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelReset)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default ResetDialog;
