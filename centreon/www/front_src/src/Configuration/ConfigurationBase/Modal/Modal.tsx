import { Modal } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { equals } from 'ramda';
import { useStyles } from './Modal.styles';

import { configurationAtom } from '../../atoms';
import { modalStateAtom } from './atoms';

import {
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom
} from '../../HostGroups/atoms';
import { labelAddResource, labelUpdateResource } from '../translatedLabels';

interface Props {
  Form: ({ onSubmit, onCancel, mode }) => JSX.Element;
}

const FormModal = ({ Form }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const isFormDirty = useAtomValue(isFormDirtyAtom);
  const setIsCloseConfirmationDialogOpen = useSetAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const [modalState, setModalState] = useAtom(modalStateAtom);

  const configuration = useAtomValue(configurationAtom);
  const resourceType = configuration?.resourceType;

  useEffect(() => {
    const mode = searchParams.get('mode');
    const id = searchParams.get('id');

    if (mode) {
      setModalState({
        isOpen: true,
        mode: mode as 'add' | 'edit',
        id: Number(id)
      });
    }
  }, [searchParams, setModalState]);

  const close = () => {
    if (isFormDirty) {
      setIsCloseConfirmationDialogOpen(true);

      return;
    }

    setSearchParams({});

    setModalState({ ...modalState, isOpen: false, id: null });
  };

  const labelHeader = equals(modalState.mode, 'add')
    ? labelAddResource
    : labelUpdateResource;

  return (
    <Modal
      data-testid="Modal"
      open={modalState.isOpen}
      size="xlarge"
      onClose={close}
    >
      <Modal.Header data-testid="Modal-header">
        <Typography className={classes.modalHeader}>
          {t(labelHeader(resourceType))}
        </Typography>
      </Modal.Header>
      <Modal.Body>
        <Form
          onSubmit={() => undefined}
          onCancel={() => undefined}
          mode={modalState.mode}
        />
      </Modal.Body>
    </Modal>
  );
};

export default FormModal;
