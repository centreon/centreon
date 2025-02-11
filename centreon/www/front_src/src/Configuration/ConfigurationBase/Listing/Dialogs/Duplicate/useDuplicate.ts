import { useMemo, useState } from 'react';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { capitalize } from '@mui/material';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import pluralize from 'pluralize';
import { equals, isEmpty, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../../../atoms';
import { hostGroupsToDuplicateAtom, selectedRowsAtom } from '../../atoms';

import {
  labelDuplicateResource,
  labelDuplicateResourceConfirmation,
  labelDuplicateResourcesConfirmation,
  labelResourceDuplicated
} from '../../../translatedLabels';
import { useDuplicate as useDuplicateRequest } from '../../api';

interface UseDuplicateProps {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  duplicatesCount: number;
  changeDuplicateCount: (inputValue: number) => void;
  isOpened: boolean;
  bodyContent: string;
  headerContent: string;
}

const useDuplicate = (): UseDuplicateProps => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [duplicatesCount, setDuplicatesCount] = useState(1);
  const [hostGroupsToDuplicate, setHostGroupsToDuplicate] = useAtom(
    hostGroupsToDuplicateAtom
  );
  const configuration = useAtomValue(configurationAtom);
  const setSelectedRows = useSetAtom(selectedRowsAtom);

  const name = hostGroupsToDuplicate[0]?.name;
  const count = hostGroupsToDuplicate.length;

  const resourceType = configuration?.resourceType as string;
  const labelResourceType = pluralize(resourceType, count);

  const isOpened = useMemo(
    () => !isEmpty(hostGroupsToDuplicate),
    [hostGroupsToDuplicate]
  );

  const resetSelections = (): void => {
    setSelectedRows([]);
    setHostGroupsToDuplicate([]);
  };

  const changeDuplicateCount = (inputValue: number) =>
    setDuplicatesCount(inputValue);

  const { duplicateMutation, isMutating } = useDuplicateRequest();

  const payload = useMemo(
    () => ({
      ids: pluck('id', hostGroupsToDuplicate),
      nbDuplicates: duplicatesCount
    }),
    [hostGroupsToDuplicate, duplicatesCount]
  );

  const handleApiResponse = (response) => {
    const { isError } = response as ResponseError;

    if (isError) {
      return;
    }

    resetSelections();
    showSuccessMessage(
      t(labelResourceDuplicated(capitalize(labelResourceType)))
    );
  };

  const confirm = (): void => {
    duplicateMutation(payload).then(handleApiResponse);
  };

  const bodyContent = useMemo(
    () =>
      equals(count, 1)
        ? t(labelDuplicateResourceConfirmation(labelResourceType), { name })
        : t(labelDuplicateResourcesConfirmation(labelResourceType), { count }),
    [name, count, labelResourceType]
  );

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
