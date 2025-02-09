import { Modal } from '@centreon/ui/components';
import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { labelAddHostGroup, labelUpdateHostGroup } from '../translatedLabels';
import { useStyles } from './Modal.styles';
import { dialogStateAtom } from './atoms';

const HostGroupModal = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const navigate = useNavigate();

  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const close = () => {
    navigate('');

    setDialogState({ ...dialogState, isOpen: false, id: null });
  };

  const labelHeader = equals(dialogState.variant, 'create')
    ? labelAddHostGroup
    : labelUpdateHostGroup;

  return (
    <Modal
      data-testid="Modal"
      open={dialogState.isOpen}
      size="xlarge"
      onClose={close}
    >
      <div className={classes.modal}>
        <Modal.Header data-testid="Modal-header">{t(labelHeader)}</Modal.Header>

        <Modal.Body></Modal.Body>
      </div>
    </Modal>
  );
};

export default HostGroupModal;
