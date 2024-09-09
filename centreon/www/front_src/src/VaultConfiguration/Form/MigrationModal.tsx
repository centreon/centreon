import { Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { Trans, useTranslation } from 'react-i18next';
import {
  labelByExecutingThisScript,
  labelMigrationCanTakeSeveralMinutes,
  labelMigrationScript,
  labelMigrationScriptExportCredentials
} from '../translatedLabels';

interface Props {
  isOpen: boolean;
  close: () => void;
}

const MigrationModal = ({ isOpen, close }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Modal open onClose={close} size="large">
      <Modal.Header>{t(labelMigrationScript)}</Modal.Header>
      <Modal.Body>
        <Typography>{t(labelMigrationScriptExportCredentials)}</Typography>
        <Typography>
          <Trans t={t}>{labelByExecutingThisScript}</Trans>
        </Typography>
        <Typography>{t(labelMigrationCanTakeSeveralMinutes)}</Typography>
      </Modal.Body>
    </Modal>
  );
};

export default MigrationModal;
