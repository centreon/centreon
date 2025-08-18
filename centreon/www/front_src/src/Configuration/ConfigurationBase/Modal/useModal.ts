import { capitalize } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import { ResponseError, useSnackbar } from '@centreon/ui';

import {
  configurationAtom,
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom,
  modalStateAtom
} from '../atoms';

import {
  useCreate as useCreateRequest,
  useGetOne as useGetDetails,
  useUpdate as useUpdateRequest
} from '../api';

import {
  labelModalTitle,
  labelResourceCreated,
  labelResourceUpdated
} from '../translatedLabels';

interface UseModalState {
  labelHeader: string;
  submit: (
    values,
    {
      setSubmitting
    }: {
      setSubmitting;
    }
  ) => void;
  close: () => void;
  isOpen: boolean;
  mode: 'add' | 'edit';
  id: number;
  initialValues;
  isLoading: boolean;
}

const useModal = ({ defaultValues, hasWriteAccess }): UseModalState => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

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

  const { data, isLoading } = useGetDetails({
    id: modalState.id
  });

  const initialValues =
    data && equals(modalState.mode, 'edit') ? data : defaultValues;

  const { createMutation } = useCreateRequest();
  const { updateMutation } = useUpdateRequest();

  useEffect(() => {
    const mode = searchParams.get('mode');
    const id = searchParams.get('id');

    if (mode) {
      setModalState({
        isOpen: true,
        mode: mode as 'add' | 'edit',
        id: id ? Number(id) : null
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

  const labelHeader = t(
    labelModalTitle({
      action: !hasWriteAccess ? 'View' : isAddMode ? 'Add' : 'Modify',
      type: resourceType
    })
  );

  return {
    labelHeader,
    submit,
    close,
    isOpen: modalState.isOpen,
    mode: modalState.mode,
    id: modalState.id,
    initialValues,
    isLoading
  };
};

export default useModal;
