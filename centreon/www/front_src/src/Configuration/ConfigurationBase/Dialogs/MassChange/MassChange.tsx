import { capitalize, Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';

import { useAtom, useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import {
  configurationAtom,
  formActionsAtom,
  formStateAtom,
  isMassChangeConfirmationDialogOpenAtom
} from '../../atoms';
import {
  labelApply,
  labelApplyChangesTo,
  labelCancel,
  labelMassChange
} from '../../translatedLabels';

// States the count before anything is written: a check-all selects the whole
// page, the platform's own hosts included.
const MassChangeDialog = (): ReactElement => {
  const { t } = useTranslation();

  const [isOpened, setIsOpened] = useAtom(
    isMassChangeConfirmationDialogOpenAtom
  );
  const formActions = useAtomValue(formActionsAtom);
  const { selection = [] } = useAtomValue(formStateAtom);
  const configuration = useAtomValue(configurationAtom);

  const count = selection.length;
  const type = pluralize(
    capitalize(configuration?.resourceType ?? ''),
    count
  ).toLowerCase();

  const close = (): void => setIsOpened(false);

  const confirm = (): void => {
    formActions?.submit();
    setIsOpened(false);
  };

  return (
    <Modal onClose={close} open={isOpened} size="large">
      <Modal.Header>{t(labelMassChange)}</Modal.Header>
      <Modal.Body>
        <Typography data-testid="mass-change-confirmation">
          {t(labelApplyChangesTo({ count, type }))}
        </Typography>
      </Modal.Body>
      <Modal.Actions
        isDanger
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelApply)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default MassChangeDialog;
