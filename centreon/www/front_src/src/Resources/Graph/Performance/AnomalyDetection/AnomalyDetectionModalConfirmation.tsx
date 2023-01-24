import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Dialog } from '@centreon/ui';

import { labelCancel, labelSave } from '../../../translatedLabels';

interface Props {
  dataTestid: string;
  message: string;
  onCancel: (value: boolean) => void;
  onConfirm: (value: boolean) => void;
  open: boolean;
  setOpen: (value: boolean) => void;
  title: string;
}

const AnomalyDetectionModalConfirmation = ({
  open,
  setOpen,
  onCancel,
  onConfirm,
  dataTestid,
  message,
  title
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const cancel = (): void => {
    onCancel(true);
    setOpen(false);
  };

  return (
    <Dialog
      data-testid={dataTestid}
      labelCancel={t(labelCancel)}
      labelConfirm={t(labelSave)}
      labelTitle={title}
      open={open}
      onCancel={cancel}
      onClose={(): void => setOpen(false)}
      onConfirm={(): void => onConfirm(true)}
    >
      <Typography>{message}</Typography>
    </Dialog>
  );
};
export default AnomalyDetectionModalConfirmation;
