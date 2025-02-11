import { Modal } from '@centreon/ui/components';
import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { labelAddResource, labelUpdateResource } from '../translatedLabels';
import { useStyles } from './Modal.styles';
import { dialogStateAtom } from './atoms';

import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../atoms';

interface Props {
  Form: JSX.Element;
}

const FormModal = ({ Form }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const navigate = useNavigate();

  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const configuration = useAtomValue(configurationAtom);
  const resourceType = configuration?.resourceType;

  const close = () => {
    navigate('');

    setDialogState({ ...dialogState, isOpen: false, id: null });
  };

  const labelHeader = equals(dialogState.variant, 'create')
    ? labelAddResource
    : labelUpdateResource;

  return (
    <Modal
      data-testid="Modal"
      open={dialogState.isOpen}
      size="xlarge"
      onClose={close}
    >
      <div className={classes.modal}>
        <Modal.Header data-testid="Modal-header">
          {t(labelHeader(resourceType))}
        </Modal.Header>

        <Modal.Body>{Form}</Modal.Body>
      </div>
    </Modal>
  );
};

export default FormModal;
