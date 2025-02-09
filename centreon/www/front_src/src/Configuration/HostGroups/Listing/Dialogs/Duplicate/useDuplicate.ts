import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { ResponseError, useSnackbar } from '@centreon/ui';
import { useState } from 'react';
import { hostGroupsToDuplicateAtom, selectedRowsAtom } from '../../atoms';
import { NamedEntity } from '../../models';

import { equals, pluck } from 'ramda';
import {
  labelHostGroupDuplicated,
  labelHostGroupsDuplicated
} from '../../../translatedLabels';
import { useDuplicate as useDuplicateRequest } from '../../api';

interface UseDuplicateProps {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  duplicatesCount: number;
  changeDuplicateCount: (inputValue: number) => void;
  hostGroupsToDuplicate: Array<NamedEntity>;
  count: number;
  name: string;
}

const useDuplicate = (): UseDuplicateProps => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const [duplicatesCount, setDuplicatesCount] = useState(1);
  const [hostGroupsToDuplicate, setHostGroupsToDuplicate] = useAtom(
    hostGroupsToDuplicateAtom
  );
  const setSelectedRows = useSetAtom(selectedRowsAtom);

  const { duplicateMutation, isMutating } = useDuplicateRequest();

  const count = hostGroupsToDuplicate.length;
  const name = hostGroupsToDuplicate[0]?.name;

  const changeDuplicateCount = (inputValue: number) =>
    setDuplicatesCount(inputValue);

  const resetSelections = (): void => {
    setHostGroupsToDuplicate([]);
    setSelectedRows([]);
  };

  const confirm = (): void => {
    duplicateMutation({
      ids: pluck('id', hostGroupsToDuplicate),
      nbDuplicates: duplicatesCount
    }).then((response) => {
      const { isError } = response as ResponseError;
      if (isError) {
        return;
      }

      resetSelections();
      showSuccessMessage(
        t(
          equals(count, 1)
            ? labelHostGroupDuplicated
            : labelHostGroupsDuplicated
        )
      );
    });
  };

  return {
    confirm,
    close: resetSelections,
    isMutating,
    duplicatesCount,
    changeDuplicateCount,
    hostGroupsToDuplicate,
    count,
    name
  };
};

export default useDuplicate;
