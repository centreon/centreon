import { useEffect } from 'react';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { Typography, capitalize } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import Form from './Form/Form';

import { configurationAtom } from '../../atoms';
import {
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom,
  modalStateAtom
} from '../atoms';

import {
  useCreate as useCreateRequest,
  useUpdate as useUpdateRequest
} from '../api';

import {
  labelAddResource,
  labelResourceCreated,
  labelResourceUpdated,
  labelUpdateResource
} from '../translatedLabels';

import { useStyles } from './Modal.styles';

const FormModal = ({ form }): JSX.Element => {
  const { showSuccessMessage } = useSnackbar();

  const { t } = useTranslation();
  const { classes } = useStyles();

  const [searchParams, setSearchParams] = useSearchParams(
    window.location.search
  );

  const [modalState, setModalState] = useAtom(modalStateAtom);
  const isFormDirty = useAtomValue(isFormDirtyAtom);
  const setIsCloseConfirmationDialogOpen = useSetAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType;
  const adapter = configuration?.api?.adapter;

  const labelResourceType = capitalize(resourceType as string);
  const isAddMode = equals(modalState.mode, 'add');

  const { createMutation } = useCreateRequest();
  const { updateMutation } = useUpdateRequest();

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

  const reset = (): void => {
    setSearchParams({});
    setModalState({ ...modalState, isOpen: false, id: null });
  };

  const close = () => {
    if (isFormDirty) {
      setIsCloseConfirmationDialogOpen(true);

      return;
    }

    reset();
  };

  const handleApiSuccess = (response): void => {
    const { isError } = response as ResponseError;

    if (isError) {
      return;
    }

    reset();

    showSuccessMessage(
      t(
        isAddMode
          ? labelResourceCreated(labelResourceType)
          : labelResourceUpdated(labelResourceType)
      )
    );
  };

  const submit = (values, { setSubmitting }): void => {
    const payload = adapter(values);
    const mutate = isAddMode
      ? createMutation
      : updateMutation(modalState.id as number);

    mutate(payload)
      .then(handleApiSuccess)
      .finally(() => {
        setSubmitting(false);
      });
  };

  const labelHeader = isAddMode ? labelAddResource : labelUpdateResource;

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
          onSubmit={submit}
          onCancel={close}
          mode={modalState.mode}
          id={modalState.id}
          inputs={form?.inputs}
          groups={form?.groups}
          validationSchema={form?.validationSchema}
          defaultValues={form?.defaultValues}
        />
      </Modal.Body>
    </Modal>
  );
};

export default FormModal;
