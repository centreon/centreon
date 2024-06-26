import { useCallback, useMemo } from 'react';

import { useAtom } from 'jotai';
import { append, equals, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { userAtom } from '@centreon/ui-context';
import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { userParametersEndpoint } from '../../../../api/endpoints';
import { routerParams } from '../../hooks/useDashboardDetails';
import { labelDashboardMarkedAsFavorite } from '../../translatedLabels';

interface ToggleFavoritesProps {
  dashboardId: number;
  favorites: Array<number>;
}

const addToFavorites = ({
  dashboardId,
  favorites
}: ToggleFavoritesProps): Array<number> => append(dashboardId, favorites);
const removeFromFavorites = ({
  dashboardId,
  favorites
}: ToggleFavoritesProps): Array<number> =>
  reject((dashboard) => equals(dashboard, Number(dashboardId)), favorites);

interface UseFavoriteState {
  isFavorite?: boolean;
  toggleFavorite: () => void;
}

export const useFavorite = (): UseFavoriteState => {
  const { t } = useTranslation();
  const [user, setUser] = useAtom(userAtom);

  const { dashboardId } = routerParams.useParams();
  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => userParametersEndpoint,
    method: Method.PATCH,
    onError: () => {
      setUser((currentUser) => ({
        ...currentUser,
        dashboard: currentUser.dashboard
          ? {
              ...currentUser.dashboard,
              favorites: removeFromFavorites({
                dashboardId: Number(dashboardId),
                favorites: currentUser.dashboard.favorites
              })
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
              favorites: addToFavorites({
                dashboardId: Number(dashboardId),
                favorites: currentUser.dashboard.favorites
              })
            }
          : null
      }));
    },
    onSuccess: () => {
      showSuccessMessage(t(labelDashboardMarkedAsFavorite));
    }
  });

  const isFavorite = useMemo(
    () => user?.dashboard?.favorites.includes(Number(dashboardId)),
    [user]
  );

  const toggleFavorite = useCallback(() => {
    mutateAsync({
      payload: {
        dashboard: {
          ...(user?.dashboard || {}),
          favorites: isFavorite
            ? removeFromFavorites({
                dashboardId: Number(dashboardId),
                favorites: user.dashboard?.favorites || []
              })
            : addToFavorites({
                dashboardId: Number(dashboardId),
                favorites: user.dashboard?.favorites || []
              })
        }
      }
    });
  }, [user?.dashboard?.favorites]);

  return {
    isFavorite,
    toggleFavorite
  };
};
