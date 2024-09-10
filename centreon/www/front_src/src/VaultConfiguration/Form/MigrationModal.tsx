import { CopyCommand, Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { Trans, useTranslation } from 'react-i18next';
import {
  labelByExecutingThisScript,
  labelExecuteThisCommandAsRoot,
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
    <Modal open={isOpen} onClose={close} size="large">
      <Modal.Header>{t(labelMigrationScript)}</Modal.Header>
      <Modal.Body>
        <Typography>{t(labelMigrationScriptExportCredentials)}</Typography>
        <Typography>
          <Trans t={t}>{labelByExecutingThisScript}</Trans>
        </Typography>
        <Typography>{t(labelMigrationCanTakeSeveralMinutes)}</Typography>
        <CopyCommand
          text={`# ${t(labelExecuteThisCommandAsRoot)}
/usr/share/centreon/bin/migrateCredentials.php`}
          language="bash"
          commandToCopy="/usr/share/centreon/bin/migrateCredentials.php"
        />
      </Modal.Body>
    </Modal>
  );
};

export default MigrationModal;
