import { useAtom, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { ResponseError, useSnackbar } from '@centreon/ui';

import { equals, pluck } from 'ramda';
import { hostGroupsToDeleteAtom, selectedRowsAtom } from '../../atoms';
import { NamedEntity } from '../../models';

import {
  labelResourceDeleted,
  labelResourcesDeleted
} from '../../../translatedLabels';
import {
  useDeleteOne as useDeleteOneRequest,
  useDelete as useDeleteRequest
} from '../../api';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  hostGroupsToDelete: Array<NamedEntity>;
  count: number;
  name: string;
}

const useDelete = ({ resourceType }): UseDeleteState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const { deleteMutation, isMutating } = useDeleteRequest();
  const { deleteOneMutation, isMutating: isMutatingOne } =
    useDeleteOneRequest();

  const setSelectedRows = useSetAtom(selectedRowsAtom);
  const [hostGroupsToDelete, setHostGroupsToDelete] = useAtom(
    hostGroupsToDeleteAtom
  );

  const name = hostGroupsToDelete[0]?.name;
  const count = hostGroupsToDelete.length;
  const ids = pluck('id', hostGroupsToDelete);

  const resetSelections = (): void => {
    setHostGroupsToDelete([]);
    setSelectedRows([]);
  };

  const confirm = (): void => {
    equals(count, 1)
      ? deleteOneMutation({ id: ids[0] }).then((response) => {
          const { isError } = response as ResponseError;
          if (isError) {
            return;
          }

          resetSelections();
          showSuccessMessage(t(labelResourceDeleted, { resourceType }));
        })
      : deleteMutation({ ids }).then((response) => {
          const { isError } = response as ResponseError;
          if (isError) {
            return;
          }

          resetSelections();
          showSuccessMessage(t(labelResourcesDeleted, { resourceType }));
        });
  };

  return {
    confirm,
    close: resetSelections,
    isMutating: isMutating || isMutatingOne,
    hostGroupsToDelete,
    count,
    name
  };
};

export default useDelete;
