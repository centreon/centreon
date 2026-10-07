// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { capitalize } from '@mui/material';

import { ResponseError, useBulkResponse, useSnackbar } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import type { MassChangeForm } from '../../models';
import {
  useCreate as useCreateRequest,
  useGetOne as useGetDetails,
  useMassUpdate,
  useUpdate as useUpdateRequest
} from '../api';
import {
  configurationAtom,
  formStateAtom,
  isCloseConfirmationDialogOpenAtom,
  isFormDirtyAtom
} from '../atoms';
import { selectedRowsAtom } from '../Listing/atoms';
import {
  labelFailedToUpdateResources,
  labelFailedToUpdateSomeResources,
  labelMassChange,
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
  mode: 'add' | 'edit' | 'massChange';
  id: number;
  initialValues;
  isLoading: boolean;
}

interface Props {
  defaultValues: object;
  hasWriteAccess: boolean;
  massChangeForm?: MassChangeForm;
}

const useForm = ({
  defaultValues,
  hasWriteAccess,
  massChangeForm
}: Props): UseFormState => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();
  const handleBulkResponse = useBulkResponse();
  const setSelectedRows = useSetAtom(selectedRowsAtom);

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
  const isMassChangeMode = equals(formState.mode, 'massChange');
  const selection = formState.selection ?? [];
  const massChangeIds = selection.map(({ id }) => id);

  const { data, isLoading } = useGetDetails({
    id: formState.id
  });

  const getInitialValues = () => {
    if (isMassChangeMode) {
      return massChangeForm?.defaultValues;
    }

    return data && equals(formState.mode, 'edit') ? data : defaultValues;
  };

  const initialValues = getInitialValues();

  const { createMutation } = useCreateRequest();
  const { updateMutation } = useUpdateRequest();
  const { massUpdateMutation } = useMassUpdate();

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

  const submitMassChange = (values, { setSubmitting }): void => {
    const labelResources = pluralize(labelResourceType, massChangeIds.length);

    massUpdateMutation({
      ids: massChangeIds,
      payload: massChangeForm.adapter(values)
    })
      .then((response) => {
        const { isError, results } = response as ResponseError;

        if (isError) {
          return;
        }

        handleBulkResponse({
          data: results,
          items: selection,
          labelFailed: t(labelFailedToUpdateResources(labelResources)),
          labelSuccess: t(labelResourceUpdated(labelResources)),
          labelWarning: t(labelFailedToUpdateSomeResources)
        });

        // A partial failure keeps the panel open on what was typed.
        if (results.every(({ status }) => status < 300)) {
          setSelectedRows([]);
          reset();
        }
      })
      .finally(() => {
        setSubmitting(false);
      });
  };

  const submit = (values, { setSubmitting }): void => {
    if (isMassChangeMode) {
      submitMassChange(values, { setSubmitting });

      return;
    }

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

  const labelHeader = isMassChangeMode
    ? `${t(labelMassChange)} (${massChangeIds.length})`
    : t(
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
