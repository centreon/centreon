import { useMemo } from 'react';

import {
  ResponseError,
  truncate,
  useBulkResponse,
  useSnackbar
} from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { equals, isEmpty, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../../atoms';
import { resourcesToDeleteAtom, selectedRowsAtom } from '../../Listing/atoms';

import {
  useDeleteOne as useDeleteOneRequest,
  useDelete as useDeleteRequest
} from '../../api';

import {
  labelDeleteResource,
  labelDeleteResourceConfirmation,
  labelDeleteResourcesConfirmation,
  labelFailedToDeleteResources,
  labelFailedToDeleteSomeResources,
  labelResourceDeleted
} from '../../translatedLabels';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  isOpened: boolean;
  headerContent: string;
  bodyContent: { label: string; value: object };
}

const useDelete = (): UseDeleteState => {
  const { t } = useTranslation();
  const handleBulkResponse = useBulkResponse();
  const { showSuccessMessage } = useSnackbar();

  const [resourcesToDelete, setResourcesToDelete] = useAtom(
    resourcesToDeleteAtom
  );

  const setSelectedRows = useSetAtom(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);

  const name = truncate({ content: resourcesToDelete[0]?.name, maxLength: 40 });
  const count = resourcesToDelete.length;
  const ids = pluck('id', resourcesToDelete);

  const resourceType = configuration?.resourceType as string;
  const labelResourceType = pluralize(resourceType, count);

  const isOpened = useMemo(
    () => !isEmpty(resourcesToDelete),
    [resourcesToDelete]
  );

  const resetSelections = (): void => {
    setSelectedRows([]);
    setResourcesToDelete([]);
  };

  const { deleteMutation, isMutating } = useDeleteRequest();
  const { deleteOneMutation, isMutating: isMutatingOne } =
    useDeleteOneRequest();

  const handleApiResponse = (response) => {
    const { isError, results } = response as ResponseError;
    if (isError) {
      return;
    }

    if (equals(count, 1)) {
      showSuccessMessage(
        t(labelResourceDeleted(capitalize(labelResourceType)))
      );

      resetSelections();

      return;
    }

    handleBulkResponse({
      data: results,
      labelWarning: t(labelFailedToDeleteSomeResources),
      labelFailed: t(labelFailedToDeleteResources(labelResourceType)),
      labelSuccess: t(labelResourceDeleted(capitalize(labelResourceType))),
      items: resourcesToDelete
    });

    resetSelections();
  };

  const confirm = (): void => {
    equals(count, 1)
      ? deleteOneMutation({ id: ids[0] }).then(handleApiResponse)
      : deleteMutation({ ids }).then(handleApiResponse);
  };

  const bodyContent = {
    label: equals(count, 1)
      ? labelDeleteResourceConfirmation(labelResourceType)
      : labelDeleteResourcesConfirmation(labelResourceType),
    value: equals(count, 1) ? { name } : { count }
  };

  const headerContent = useMemo(
    () => t(labelDeleteResource(labelResourceType)),
    [labelResourceType]
  );

  return {
    confirm,
    close: resetSelections,
    isMutating: isMutating || isMutatingOne,
    isOpened,
    headerContent,
    bodyContent
  };
};

export default useDelete;
