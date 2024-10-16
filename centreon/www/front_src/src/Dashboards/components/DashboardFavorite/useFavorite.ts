import { useCallback } from 'react';

import { useAtom } from 'jotai';
import { append, equals, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { profileAtom } from '@centreon/ui-context';
import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { profileEndpoint } from '../../../api/endpoint';
import { labelDashboardMarkedAsFavorite, labelDashboardUnmarkedAsFavorite } from '../../translatedLabels';

interface ToggleFavoritesProps {
  dashboardId: number;
  favoriteDashboards: Array<number>;
}

const addToFavorites = ({
  dashboardId,
  favoriteDashboards
}: ToggleFavoritesProps): Array<number> => append(dashboardId, favoriteDashboards);

const removeFromFavorites = ({
  dashboardId,
  favoriteDashboards
}: ToggleFavoritesProps): Array<number> =>
  reject((dashboard) => equals(dashboard, Number(dashboardId)), favoriteDashboards);


const getNewFavoriteList = (isFavorite, dashboardId, favoriteDashboards ) => {
  return isFavorite
  ? removeFromFavorites({
      dashboardId,
      favoriteDashboards
    })
  : addToFavorites({
      dashboardId,
      favoriteDashboards
    })
}

interface UseFavoriteState {
  toggleFavorite: () => void;
}

interface UseFavoriteProps { 
  isFavorite ?: boolean;
  dashboardId : number;
}

export const useFavorite = ({isFavorite, dashboardId } : UseFavoriteProps): UseFavoriteState => {
  const { t } = useTranslation();
  const [profile, setProfile] = useAtom(profileAtom);

  const { showSuccessMessage } = useSnackbar();

  const { mutateAsync } = useMutationQuery({
    getEndpoint: () => profileEndpoint,
    method: Method.PATCH,
    onError: () => {
      setProfile((currentProfile) => ({
        ...currentProfile,
        favoriteDashboards: getNewFavoriteList(isFavorite, Number(dashboardId) , profile?.favoriteDashboards || []) 
      }));
    },
    onMutate: () => {
      setProfile((currentProfile) => ({
        ...currentProfile,
        favoriteDashboards: getNewFavoriteList(isFavorite, Number(dashboardId) , profile?.favoriteDashboards || []) 

      }));
    },
    onSuccess: () => {
      showSuccessMessage(t(isFavorite ? labelDashboardMarkedAsFavorite : labelDashboardUnmarkedAsFavorite  ));
    }
  });

  const toggleFavorite = useCallback(() => {
    mutateAsync({
      payload: {
        favorite_dashboards:  getNewFavoriteList(isFavorite, Number(dashboardId) , profile?.favoriteDashboards || []) 
      }
    });
  }, [profile?.favoriteDashboards]);

  return {
    toggleFavorite
  };
};