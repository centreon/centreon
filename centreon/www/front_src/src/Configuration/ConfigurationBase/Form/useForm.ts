// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { capitalize } from '@mui/material';

import { ResponseError, useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import {
  useCreate as useCreateRequest,
  useGetOne as useGetDetails,
  useUpdate as useUpdateRequest
} from '../api';
import {
  configurationAtom,
  formStateAtom,
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom
} from '../atoms';
import {
  labelModalTitle,
  labelResourceCreated,
  labelResourceUpdated
} from '../translatedLabels';

interface UseFormState {
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

const useForm = ({ defaultValues, hasWriteAccess }): UseFormState => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();

  const [, setSearchParams] = useSearchParams(window.location.search);

  const [formState, setFormState] = useAtom(formStateAtom);
  const isFormDirty = useAtomValue(isFormDirtyAtom);
  const setIsCloseConfirmationDialogOpen = useSetAtom(
    isCloseConfirmationDialogOpenAtom
  );
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType;
  const adapter = configuration?.api?.adapter;

  const labelResourceType = capitalize(resourceType as string);
  const isAddMode = equals(formState.mode, 'add');

  const { data, isLoading } = useGetDetails({
    id: formState.id
  });

  const initialValues =
    data && equals(formState.mode, 'edit') ? data : defaultValues;

  const { createMutation } = useCreateRequest();
  const { updateMutation } = useUpdateRequest();

  const reset = (): void => {
    setSearchParams({});
    setFormState({ ...formState, id: null, isOpen: false });
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
      : updateMutation(formState.id as number);

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
    close,
    id: formState.id,
    initialValues,
    isLoading,
    isOpen: formState.isOpen,
    labelHeader,
    mode: formState.mode,
    submit
  };
};

export default useForm;
