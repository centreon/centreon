import { useMemo } from 'react';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { equals, isEmpty, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../../../atoms';
import { hostGroupsToDeleteAtom, selectedRowsAtom } from '../../atoms';

import {
  useDeleteOne as useDeleteOneRequest,
  useDelete as useDeleteRequest
} from '../../../api';

import {
  labelDeleteResource,
  labelDeleteResourceConfirmation,
  labelDeleteResourcesConfirmation,
  labelResourceDeleted
} from '../../../translatedLabels';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  count: number;
  name: string;
  isOpened: boolean;
  headerContent: string;
  bodyContent: string;
}

const useDelete = (): UseDeleteState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [hostGroupsToDelete, setHostGroupsToDelete] = useAtom(
    hostGroupsToDeleteAtom
  );

  const setSelectedRows = useSetAtom(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);

  const name = hostGroupsToDelete[0]?.name;
  const count = hostGroupsToDelete.length;
  const ids = pluck('id', hostGroupsToDelete);

  const resourceType = configuration?.resourceType as string;
  const labelResourceType = pluralize(resourceType, count);

  const isOpened = useMemo(
    () => !isEmpty(hostGroupsToDelete),
    [hostGroupsToDelete]
  );

  const resetSelections = (): void => {
    setSelectedRows([]);
    setHostGroupsToDelete([]);
  };

  const { deleteMutation, isMutating } = useDeleteRequest();
  const { deleteOneMutation, isMutating: isMutatingOne } =
    useDeleteOneRequest();

  const handleApiResponse = (response) => {
    const { isError } = response as ResponseError;
    if (isError) {
      return;
    }

    resetSelections();

    showSuccessMessage(t(labelResourceDeleted(capitalize(labelResourceType))));
  };

  const confirm = (): void => {
    equals(count, 1)
      ? deleteOneMutation({ id: ids[0] }).then(handleApiResponse)
      : deleteMutation({ ids }).then(handleApiResponse);
  };

  const bodyContent = equals(count, 1)
    ? t(labelDeleteResourceConfirmation(labelResourceType), { name })
    : t(labelDeleteResourcesConfirmation(labelResourceType), {
        count
      });

  const headerContent = useMemo(
    () => t(labelDeleteResource(labelResourceType)),
    [labelResourceType]
  );

  return {
    confirm,
    close: resetSelections,
    isMutating: isMutating || isMutatingOne,
    count,
    name,
    isOpened,
    headerContent,
    bodyContent
  };
};

export default useDelete;
