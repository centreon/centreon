import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { labelFailedToSaveShares, labelSharesSaved } from '../translatedLabels';

import { dashboardSharesEndpoint } from './endpoints';
import { DashboardRole, UserRole, resource } from './models';

interface SharesToAPI {
  contact_groups: Array<{
    id: number;
    role: DashboardRole;
  }>;
  contacts: Array<{
    id: number;
    role: DashboardRole;
  }>;
}

interface UseUpdateAccessRightsState {
  isMutating: boolean;
  updateAccessRights: (
    shares: Array<UserRole & { isContactGroup: boolean }>
  ) => void;
}

interface Props {
  close: () => void;
  dashboardId?: string | number;
}

export const useUpdateAccessRights = ({
  dashboardId,
  close
}: Props): UseUpdateAccessRightsState => {
  const { t } = useTranslation();
  const { showSuccessMessage, showErrorMessage } = useSnackbar();
  const queryClient = useQueryClient();

  const { mutateAsync, isMutating } = useMutationQuery<
    SharesToAPI,
    { id: number | string }
  >({
    getEndpoint: ({ id }) => dashboardSharesEndpoint(id),
    method: Method.PUT,
    onError: () => {
      showErrorMessage(t(labelFailedToSaveShares));
    },
    onMutate: () => {
      queryClient.cancelQueries({ queryKey: [resource.dashboards] });
      queryClient.cancelQueries({ queryKey: [resource.dashboard] });
    },
    onSuccess: () => {
      showSuccessMessage(t(labelSharesSaved));
      queryClient.invalidateQueries({ queryKey: [resource.dashboards] });
      queryClient.invalidateQueries({ queryKey: [resource.dashboard] });
      close();
    }
  });

  const updateAccessRights = async (
    shares: Array<UserRole & { isContactGroup: boolean }>
  ): Promise<void> => {
    const contacts = shares
      .filter(({ isContactGroup }) => !isContactGroup)
      .map(({ id, role }) => ({ id, role }));
    const contactGroups = shares
      .filter(({ isContactGroup }) => isContactGroup)
      .map(({ id, role }) => ({ id, role }));

    const data = await mutateAsync({
      _meta: { id: dashboardId },
      payload: {
        contact_groups: contactGroups,
        contacts
      }
    });

    return data?.isError;
  };

  return { isMutating, updateAccessRights };
};
