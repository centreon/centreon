import { atom } from 'jotai';
import { FavoriteDashboardListIds } from '../../../../../api/models';

export const favoriteDashboardsIdsAtom = atom<FavoriteDashboardListIds>({
  favoriteDashboards: []
});
