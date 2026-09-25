// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { capitalize } from '@mui/material';

import {
  ResponseError,
  truncate,
  useBulkResponse,
  useSnackbar
} from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import {
  complement,
  equals,
  isEmpty,
  isNotNil,
  last,
  pluck,
  propEq,
  split
} from 'ramda';
import { useMemo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router';

import {
  useDeleteOne as useDeleteOneRequest,
  useDelete as useDeleteRequest
} from '../../api';
import { configurationAtom, formStateAtom } from '../../atoms';
import { resourcesToDeleteAtom, selectedRowsAtom } from '../../Listing/atoms';
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

  const [, setSearchParams] = useSearchParams();

  const setSelectedRows = useSetAtom(selectedRowsAtom);
  const [formState, setFormState] = useAtom(formStateAtom);

  // The close runs once the request resolves, by which time the form may hold
  // another resource than the one this handler was built with.
  const formStateRef = useRef(formState);
  formStateRef.current = formState;
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

  // Ids a bulk response reports as refused, read from the response the same way
  // `useBulkResponse` reads it.
  const getFailedIds = (results): Array<number> =>
    (results ?? [])
      .filter(complement(propEq(204, 'status')))
      .map(({ href }) =>
        Number.parseInt(last(split('/', href || '')) as string, 10)
      );

  // A form left open on a resource that no longer exists would save into a
  // void, and a URL still naming it would open it again on the next visit.
  // A resource whose own deletion was refused still exists, so its form stays.
  const closeFormOnDeletedResource = (failedIds: Array<number> = []): void => {
    const currentFormState = formStateRef.current;
    const { id, isOpen } = currentFormState;

    if (!isOpen || !ids.includes(id) || failedIds.includes(id)) {
      return;
    }

    setSearchParams({});
    setFormState({ ...currentFormState, id: null, isOpen: false });
  };

  const { deleteMutation, isMutating } = useDeleteRequest();
  const {
    deleteOneMutation,
    deleteEachMutation,
    isMutating: isMutatingOne
  } = useDeleteOneRequest();

  // One request for the selection, or one each, depending on what is declared.
  const hasBulkEndpoint = isNotNil(configuration?.api?.endpoints?.delete);
  const deletesOneByItself = equals(count, 1) || !hasBulkEndpoint;

  const handleApiResponse = (response) => {
    const { isError, results } = response as ResponseError;
    if (isError) {
      return;
    }

    if (equals(count, 1)) {
      showSuccessMessage(
        t(labelResourceDeleted(capitalize(labelResourceType)))
      );

      closeFormOnDeletedResource();
      resetSelections();

      return;
    }

    handleBulkResponse({
      data: results,
      items: resourcesToDelete,
      labelFailed: t(labelFailedToDeleteResources(labelResourceType)),
      labelSuccess: t(labelResourceDeleted(capitalize(labelResourceType))),
      labelWarning: t(labelFailedToDeleteSomeResources)
    });

    closeFormOnDeletedResource(getFailedIds(results));
    resetSelections();
  };

  const confirm = (): void => {
    if (!deletesOneByItself) {
      deleteMutation({ ids }).then(handleApiResponse);

      return;
    }

    equals(count, 1)
      ? deleteOneMutation({ id: ids[0] }).then(handleApiResponse)
      : deleteEachMutation({ ids }).then(handleApiResponse);
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
    bodyContent,
    close: resetSelections,
    confirm,
    headerContent,
    isMutating: isMutating || isMutatingOne,
    isOpened
  };
};

export default useDelete;
