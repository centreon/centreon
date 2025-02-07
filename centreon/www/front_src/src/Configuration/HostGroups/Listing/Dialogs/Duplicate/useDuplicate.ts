import { useQueryClient } from '@tanstack/react-query';
import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { pluck } from 'ramda';
import { useState } from 'react';
import { duplicateHostGroupEndpoint } from '../../../api/endpoints';
import { labelHostGroupDuplicated } from '../../../translatedLabels';
import { hostGroupsToDuplicateAtom, selectedRowsAtom } from '../../atoms';
import { NamedEntity } from '../../models';

interface UseDuplicateProps {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  duplicatesCount: number;
  changeDuplicateCount: (inputValue: number) => void;
  hostGroupsToDuplicate: Array<NamedEntity>;
}

const useDuplicate = (): UseDuplicateProps => {
  const { t } = useTranslation();

  const { showSuccessMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const [duplicatesCount, setDuplicatesCount] = useState(1);

  const [hostGroupsToDuplicate, setHostGroupsToDuplicate] = useAtom(
    hostGroupsToDuplicateAtom
  );
  const setSelectedRows = useSetAtom(selectedRowsAtom);

  const changeDuplicateCount = (inputValue: number) =>
    setDuplicatesCount(inputValue);

  const close = (): void => {
    setHostGroupsToDuplicate([]);
    setSelectedRows([]);
  };

  const { isMutating, mutateAsync: duplicateHostGroupsRequest } =
    useMutationQuery({
      getEndpoint: () => duplicateHostGroupEndpoint,
      method: Method.POST,
      onSettled: close,
      onSuccess: () => {
        setHostGroupsToDuplicate([]);
        setSelectedRows([]);
        showSuccessMessage(t(labelHostGroupDuplicated));
        queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
      }
    });

  const confirm = (): void => {
    duplicateHostGroupsRequest({
      payload: {
        ids: pluck('id', hostGroupsToDuplicate),
        nb_duplicates: duplicatesCount
      }
    });
  };

  return {
    confirm,
    close,
    isMutating,
    duplicatesCount,
    changeDuplicateCount,
    hostGroupsToDuplicate
  };
};

export default useDuplicate;
