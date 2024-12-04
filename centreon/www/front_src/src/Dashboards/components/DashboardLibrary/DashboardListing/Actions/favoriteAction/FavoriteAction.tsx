import {
  IconButton,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import FavoriteIcon from '@mui/icons-material/Favorite';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { memo } from 'react';
import { dashboardsFavoriteEndpoit } from '../../../../../api/endpoints';
import { favoriteDashboardsIdsAtom } from './atoms';

// a deplacer
const labelDashboardSuccessfullyMarkedAsFavorite =
  'The dashboard successfully marked as favorite';
const labelDashboardSuccessfullyMarkedAsUnFavorite =
  'The dashboard successfully marked as unFavorite';
const labelNotMarkedAsFavorite = 'Not marked as favorite';
const labelMarkedAsFavorite = 'Marked as favorite';

interface Props {
  dashboardId: number;
  asFavorite: boolean;
}

const FavoriteAction = ({ dashboardId, asFavorite }: Props) => {
  const { showSuccessMessage } = useSnackbar();

  const { favoriteDashboards } = useAtomValue(favoriteDashboardsIdsAtom);

  const getLabel = ({ setLabel, unsetLabel }: GetLabel) => {
    if (asFavorite) {
      return unsetLabel;
    }
    return setLabel;
  };

  const labelSuccess = getLabel({
    setLabel: labelDashboardSuccessfullyMarkedAsUnFavorite,
    unsetLabel: labelDashboardSuccessfullyMarkedAsFavorite
  });

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint: () => dashboardsFavoriteEndpoit,
    method: Method.PATCH,
    onSuccess: () => showSuccessMessage(labelSuccess)
  });

  const handleFavorites = () => {
    const item = favoriteDashboards.find((id) => equals(id, dashboardId));
    if (item) {
      const payload = {
        favorite_dashboards: favoriteDashboards.filter(
          (id) => !equals(id, dashboardId)
        )
      };

      mutateAsync({ payload });

      return;
    }
    const payload = {
      favorite_dashboards: [...favoriteDashboards, dashboardId]
    };
    mutateAsync({ payload });
  };

  interface GetLabel {
    unsetLabel: string;
    setLabel: string;
  }

  const title = getLabel({
    unsetLabel: labelMarkedAsFavorite,
    setLabel: labelNotMarkedAsFavorite
  });

  return (
    <IconButton
      title={title}
      onClick={handleFavorites}
      color={asFavorite ? 'success' : 'default'}
      disabled={isMutating}
      size="small"
    >
      <FavoriteIcon fontSize="small" />
    </IconButton>
  );
};

export default memo(FavoriteAction);
