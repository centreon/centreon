import { useMemo, useState } from 'react';

import { ResponseError, truncate, useBulkResponse } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { equals, isEmpty, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  resourcesToDuplicateAtom,
  selectedRowsAtom
} from '../../Listing/atoms';
import { configurationAtom } from '../../atoms';

import { useDuplicate as useDuplicateRequest } from '../../api';
import {
  labelDuplicateResource,
  labelDuplicateResourceConfirmation,
  labelDuplicateResourcesConfirmation,
  labelFailedToDuplicateResources,
  labelFailedToDuplicateSomeResources,
  labelResourceDuplicated
} from '../../translatedLabels';

interface UseDuplicateState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  duplicatesCount: number;
  changeDuplicateCount: (inputValue: number) => void;
  isOpened: boolean;
  bodyContent: { label: string; value: object };
  headerContent: string;
}

const useDuplicate = (): UseDuplicateState => {
  const { t } = useTranslation();
  const handleBulkResponse = useBulkResponse();

  const [duplicatesCount, setDuplicatesCount] = useState(1);
  const [resourcesToDuplicate, setResourcesToDuplicate] = useAtom(
    resourcesToDuplicateAtom
  );
  const configuration = useAtomValue(configurationAtom);
  const setSelectedRows = useSetAtom(selectedRowsAtom);

  const name = truncate({
    content: resourcesToDuplicate[0]?.name,
    maxLength: 40
  });
  const count = resourcesToDuplicate.length;

  const resourceType = configuration?.resourceType as string;
  const labelResourceType = pluralize(resourceType, count);

  const isOpened = useMemo(
    () => !isEmpty(resourcesToDuplicate),
    [resourcesToDuplicate]
  );

  const resetSelections = (): void => {
    setSelectedRows([]);
    setResourcesToDuplicate([]);
  };

  const changeDuplicateCount = (inputValue: number): void =>
    setDuplicatesCount(inputValue);

  const { duplicateMutation, isMutating } = useDuplicateRequest();

  const payload = useMemo(
    () => ({
      ids: pluck('id', resourcesToDuplicate),
      nbDuplicates: duplicatesCount
    }),
    [resourcesToDuplicate, duplicatesCount]
  );

  const handleApiResponse = (response) => {
    const { isError, results } = response as ResponseError;

    if (isError) {
      return;
    }

    handleBulkResponse({
      data: results,
      labelWarning: t(labelFailedToDuplicateSomeResources),
      labelFailed: t(labelFailedToDuplicateResources(labelResourceType)),
      labelSuccess: t(labelResourceDuplicated(capitalize(labelResourceType))),
      items: resourcesToDuplicate
    });

    resetSelections();
  };

  const confirm = (): void => {
    duplicateMutation(payload).then(handleApiResponse);
  };

  const bodyContent = {
    label: equals(count, 1)
      ? labelDuplicateResourceConfirmation(labelResourceType)
      : labelDuplicateResourcesConfirmation(labelResourceType),
    value: equals(count, 1) ? { name } : { count }
  };

  const headerContent = useMemo(
    () => t(labelDuplicateResource(labelResourceType)),
    [labelResourceType]
  );

  return {
    confirm,
    close: resetSelections,
    isMutating,
    duplicatesCount,
    changeDuplicateCount,
    isOpened,
    bodyContent,
    headerContent
  };
};

export default useDuplicate;
