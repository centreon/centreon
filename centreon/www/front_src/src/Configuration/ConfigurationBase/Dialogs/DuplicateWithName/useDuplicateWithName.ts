import { capitalize } from '@mui/material';

import { type ResponseError, useSnackbar } from '@centreon/ui';

import type { FormikHelpers } from 'formik';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { isNotNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useDuplicate as useDuplicateRequest } from '../../api';
import { configurationAtom } from '../../atoms';
import {
  resourceToDuplicateWithNameAtom,
  selectedRowsAtom
} from '../../Listing/atoms';
import {
  labelDuplicateResource,
  labelResourceDuplicated
} from '../../translatedLabels';

export interface DuplicateWithNameValues {
  name: string;
}

interface UseDuplicateWithNameState {
  close: () => void;
  headerContent: string;
  initialValues: DuplicateWithNameValues;
  isOpened: boolean;
  submit: (
    values: DuplicateWithNameValues,
    bag: FormikHelpers<DuplicateWithNameValues>
  ) => Promise<void>;
}

const useDuplicateWithName = (): UseDuplicateWithNameState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [resource, setResource] = useAtom(resourceToDuplicateWithNameAtom);
  const setSelectedRows = useSetAtom(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType as string;

  const { duplicateWithNameMutation } = useDuplicateRequest();

  const close = (): void => setResource(null);

  // A refused name is shown on the field and the dialog stays open to fix it.
  const submit = async (
    { name }: DuplicateWithNameValues,
    { setFieldError }: FormikHelpers<DuplicateWithNameValues>
  ): Promise<void> => {
    if (!resource) {
      return;
    }

    const response = await duplicateWithNameMutation({ id: resource.id, name });
    const { isError, message } = response as ResponseError;

    if (isError) {
      setFieldError('name', message);

      return;
    }

    showSuccessMessage(t(labelResourceDuplicated(capitalize(resourceType))));
    setSelectedRows([]);
    close();
  };

  return {
    close,
    headerContent: t(labelDuplicateResource(resourceType)),
    initialValues: { name: `${resource?.name ?? ''}_1` },
    isOpened: isNotNil(resource),
    submit
  };
};

export default useDuplicateWithName;
