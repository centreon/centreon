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

import { getDashboardAccessRightsEndpoint } from '../api/endpoints';
import { DashboardContactAccessRights } from '../api/models';

import { DashboardShareForm, DashboardShareToAPI } from './models';
import { labelUserRolesAreUpdated } from './translatedLabels';
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
    getEndpoint: () => getDashboardAccessRightsEndpoint(dashboardId),
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
      ])[0][1] as ListingModel<DashboardContactAccessRights>;

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
      showSuccessMessage(t(labelUserRolesAreUpdated));
      queryClient.invalidateQueries({
        queryKey: ['dashboard_shares']
      });
    });
  };

  return {
    updateShares
  };
};

export default useShareUpdate;
