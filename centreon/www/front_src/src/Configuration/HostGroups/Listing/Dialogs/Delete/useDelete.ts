import { useQueryClient } from '@tanstack/react-query';
import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { equals, pluck } from 'ramda';
import {
  bulkDeleteHostGroupEndpoint,
  getHostGroupEndpoint
} from '../../../api/endpoints';
import { labelHostGroupDeleted } from '../../../translatedLabels';
import { hostGroupsToDeleteAtom } from '../../atoms';
import { NamedEntity } from '../../models';

interface UseDeleteState {
  confirm: () => void;
  close: () => void;
  isMutating: boolean;
  hostGroupsToDelete: Array<NamedEntity>;
  hostGroupsCount: number;
  hostGroupsName: string;
}

const useDelete = (): UseDeleteState => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const queryClient = useQueryClient();

  const [hostGroupsToDelete, setHostGroupsToDelete] = useAtom(
    hostGroupsToDeleteAtom
  );

  const close = (): void => setHostGroupsToDelete([]);

  const getEndpoint = () =>
    equals(hostGroupsToDelete.length, 1)
      ? getHostGroupEndpoint({ id: hostGroupsToDelete[0]?.id })
      : bulkDeleteHostGroupEndpoint;

  const { isMutating, mutateAsync: deleteHostGroup } = useMutationQuery({
    getEndpoint,
    method: equals(hostGroupsToDelete.length, 1) ? Method.DELETE : Method.POST,
    onSuccess: () => {
      close();
      showSuccessMessage(t(labelHostGroupDeleted));
      queryClient.invalidateQueries({ queryKey: ['listHostGroups'] });
    }
  });

  const confirm = (): void => {
    deleteHostGroup({ payload: { ids: pluck('id', hostGroupsToDelete) } });
  };

  const hostGroupsCount = hostGroupsToDelete.length;
  const hostGroupsName = hostGroupsToDelete[0]?.name;

  return {
    confirm,
    close,
    isMutating,
    hostGroupsToDelete,
    hostGroupsCount,
    hostGroupsName
  };
};

export default useDelete;
