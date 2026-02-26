import { capitalize } from '@mui/material';

import { ResponseError, truncate, useBulkResponse } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { equals, isEmpty, pluck } from 'ramda';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useDuplicate as useDuplicateRequest } from '../../api';
import { configurationAtom } from '../../atoms';
import {
  resourcesToDuplicateAtom,
  selectedRowsAtom
} from '../../Listing/atoms';
import {
  labelDuplicateResource,
  labelDuplicateResourceConfirmation,
  labelDuplicateResourcesConfirmation,
  labelFailedToDuplicateResources,
  labelFailedToDuplicateSomeResources,
  labelResourceDuplicated,
  labelSingleDuplicateResourceConfirmation,
  labelSingleDuplicateResourcesConfirmation
} from '../../translatedLabels';

interface UseDuplicateState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  duplicatesCount: number;
  changeDuplicateCount: (inputValue: number) => void;
  isOpened: boolean;
  getBodyContent: () => { label: string; value: object };
  headerContent: string;
  isSingleDuplicate?: boolean;
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

  const isSingleDuplicate = configuration?.api?.isSingleDuplicate;

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
      items: resourcesToDuplicate,
      labelFailed: t(labelFailedToDuplicateResources(labelResourceType)),
      labelSuccess: t(labelResourceDuplicated(capitalize(labelResourceType))),
      labelWarning: t(labelFailedToDuplicateSomeResources)
    });

    resetSelections();
  };

  const confirm = (): void => {
    duplicateMutation(payload).then(handleApiResponse);
  };

  const getBodyContent = () => {
    const isSingleResource = equals(count, 1);

    const getLabel = isSingleDuplicate
      ? isSingleResource
        ? labelSingleDuplicateResourceConfirmation
        : labelSingleDuplicateResourcesConfirmation
      : isSingleResource
        ? labelDuplicateResourceConfirmation
        : labelDuplicateResourcesConfirmation;

    return {
      label: getLabel(labelResourceType),
      value: isSingleResource ? { name } : { count }
    };
  };

  const headerContent = useMemo(
    () => t(labelDuplicateResource(labelResourceType)),
    [labelResourceType]
  );

  return {
    changeDuplicateCount,
    close: resetSelections,
    confirm,
    duplicatesCount,
    getBodyContent,
    headerContent,
    isMutating,
    isOpened,
    isSingleDuplicate
  };
};

export default useDuplicate;
