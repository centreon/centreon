import { useTranslation } from 'react-i18next';

import { DuplicateDialog as Dialog } from '@centreon/ui';
import type { ComponentColumnProps } from '@centreon/ui';

import {
  labelDiscard,
  labelDuplicate,
  labelName,
  labelDuplicateNotification
} from '../translatedLabels';

const DuplicateDialog = ({
  open,
  onCancel,
  onConfirm
}: ComponentColumnProps & { open: boolean }): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Dialog
      labelCancel={t(labelDiscard)}
      labelConfirm={t(labelDuplicate)}
      labelInput={t(labelName)}
      labelTitle={t(labelDuplicateNotification)}
      open={open}
      onCancel={onCancel}
      onConfirm={onConfirm}
    />
  );
};

export default DuplicateDialog;
