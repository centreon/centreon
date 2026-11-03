import { capitalize } from '@mui/material';

import { type ResponseError, useSnackbar } from '@centreon/ui';

import type { FormikHelpers } from 'formik';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { includes, isNotNil } from 'ramda';
import { useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';

import { useDuplicate as useDuplicateRequest } from '../../api';
import { nameErrorStatuses } from '../../api/useDuplicate';
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
  // The answer can land once the dialog was closed and reopened, on any row:
  // the row's entity can be the same object both times.
  const sessionRef = useRef(0);
  const setSelectedRows = useSetAtom(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType as string;

  const { duplicateWithNameMutation } = useDuplicateRequest();

  const close = useCallback((): void => {
    sessionRef.current += 1;
    setSelectedRows([]);
    setResource(null);
  }, [setResource, setSelectedRows]);

  // A refused name goes on the field; any error keeps the dialog open.
  const submit = async (
    { name }: DuplicateWithNameValues,
    { setFieldError }: FormikHelpers<DuplicateWithNameValues>
  ): Promise<void> => {
    if (!resource) {
      return;
    }

    const session = sessionRef.current;
    const response = await duplicateWithNameMutation({ id: resource.id, name });
    const { isError, message, statusCode } = response as ResponseError;

    if (isError) {
      if (includes(statusCode, nameErrorStatuses)) {
        setFieldError('name', message);
      }

      return;
    }

    showSuccessMessage(t(labelResourceDuplicated(capitalize(resourceType))));

    if (sessionRef.current === session) {
      close();
    }
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
