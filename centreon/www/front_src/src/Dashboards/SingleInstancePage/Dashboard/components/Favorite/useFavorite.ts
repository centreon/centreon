import { useMemo } from 'react';

import { useAtom } from 'jotai';
import { useParams } from 'react-router';
import { append, equals, reject } from 'ramda';

import { userAtom } from '@centreon/ui-context';
import { Method, useMutationQuery } from '@centreon/ui';

import { userParametersEndpoint } from '../../../../api/endpoints';

interface UseFavoriteState {
  isFavorite?: boolean;
}

export const useFavorite = (): UseFavoriteState => {
  const [user, setUser] = useAtom(userAtom);

  const { dashboardId } = useParams();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => userParametersEndpoint,
    method: Method.PATCH,
    onError: () => {
      setUser((currentUser) => ({
        ...currentUser,
        dashboard: currentUser.dashboard
          ? {
              ...currentUser.dashboard,
              favorites: reject(
                (dashboard) => equals(dashboard, Number(dashboardId)),
                currentUser.dashboard.favorites
              )
            }
          : null
      }));
    },
    onMutate: () => {
      setUser((currentUser) => ({
        ...currentUser,
        dashboard: currentUser.dashboard
          ? {
              ...currentUser.dashboard,
              favorites: append(
                Number(dashboardId),
                currentUser.dashboard.favorites
              )
            }
          : null
      }));
    }
  });

  const isFavorite = useMemo(
    () => user?.dashboard?.favorites.includes(Number(dashboardId)),
    [user]
  );

  return {
    isFavorite
  };
};
