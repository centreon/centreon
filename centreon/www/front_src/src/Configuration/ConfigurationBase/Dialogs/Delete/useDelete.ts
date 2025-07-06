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
import { equals, gt, isEmpty, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import { resourcesToDeleteAtom, selectedRowsAtom } from '../../Listing/atoms';
import { configurationAtom } from '../../atoms';

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
  headerText: string;
  getBodyText: () => string;
  getBodyTextVar: () => object;
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
  const subItemName = truncate({
    content: resourcesToDelete[0]?.subItemName,
    maxLength: 40
  });
  const count = resourcesToDelete.length;
  const ids = pluck('id', resourcesToDelete);

  const resourceType = configuration?.resourceType as string;
  const labelResourceType = pluralize(resourceType, count);

  const labelDelete = configuration?.labels?.dialogs?.delete;

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
      ? deleteOneMutation({
          id: resourcesToDelete[0].id,
          subItemId: resourcesToDelete[0]?.subItemId
        }).then(handleApiResponse)
      : deleteMutation({ ids }).then(handleApiResponse);
  };

  const headerText = useMemo(
    () =>
      !labelDelete
        ? t(labelDeleteResource(labelResourceType))
        : resourcesToDelete[0]?.subItemId
          ? labelDelete?.subItemTitle
          : labelDelete?.title,
    [labelResourceType, labelDelete?.title, labelDelete?.subItemTitle]
  );

  const getBodyText = (): string => {
    if (labelDelete) {
      return resourcesToDelete[0]?.subItemId
        ? labelDelete.subItemDescription
        : labelDelete.description;
    }

    return equals(count, 1)
      ? labelDeleteResourceConfirmation(labelResourceType)
      : labelDeleteResourcesConfirmation(labelResourceType);
  };

  const getBodyTextVar = (): object => {
    if (gt(count, 1)) {
      return { count };
    }

    if (!labelDelete) {
      return { name };
    }

    if (resourcesToDelete[0]?.subItemId) {
      return {
        [labelDelete?.name]: name,
        [labelDelete?.subItemName as string]: subItemName
      };
    }

    return { [labelDelete?.name]: name };
  };

  return {
    confirm,
    close: resetSelections,
    isMutating: isMutating || isMutatingOne,
    isOpened,
    headerText,
    getBodyTextVar,
    getBodyText
  };
};

export default useDelete;
