import { useQueryClient } from '@tanstack/react-query';
import { pick, propEq, reject } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtomValue } from 'jotai';

import {
  ListingModel,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { getDashboardSharesEndpoint } from '../api/endpoints';
import {
  DashboardShare,
  DashboardShareForm,
  DashboardShareToAPI
} from '../models';

import { labelUserRolesIsUpdated } from './translatedLabels';
import { pageAtom } from './SharesList';

interface UseUpdateSharesState {
  updateShares: (shares: Array<DashboardShareForm>) => void;
}

const formatSharesToAPI = (
  newShares: Array<DashboardShareForm>
): Array<DashboardShareToAPI> => {
  return reject(propEq('isRemoved', true), newShares).map(
    pick(['id', 'role', 'type'])
  );
};

const useShareUpdate = (dashboardId?: number): UseUpdateSharesState => {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const { showSuccessMessage } = useSnackbar();

  const page = useAtomValue(pageAtom);

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => getDashboardSharesEndpoint(dashboardId),
    method: Method.PUT,
    onError: (_, __, context) => {
      queryClient.setQueryData(
        ['dashboard_shares', page],
        context.previousSharesListing
      );
    },
    onMutate: async (variables: Array<DashboardShareToAPI>) => {
      await queryClient.cancelQueries({ queryKey: ['dashboard_shares'] });
      const previousSharesListing = queryClient.getQueriesData([
        'dashboard_shares'
      ])[0][1] as ListingModel<DashboardShare>;

      const previousShares = previousSharesListing.result;

      const shares = variables.map(({ id, role, type }) => ({
        ...previousShares.find(propEq('id', id)),
        id,
        role,
        type
      }));

      queryClient.setQueryData(['dashboard_shares', page], {
        meta: previousSharesListing.meta,
        result: shares
      });

      return {
        previousSharesListing
      };
    }
  });

  const updateShares = (newShares: Array<DashboardShareForm>): void => {
    const formattedShares = formatSharesToAPI(newShares);

    mutateAsync(formattedShares).then(() => {
      queryClient.invalidateQueries({
        queryKey: ['dashboard_shares']
      });
      showSuccessMessage(t(labelUserRolesIsUpdated));
    });
  };

  return {
    updateShares
  };
};

export default useShareUpdate;
