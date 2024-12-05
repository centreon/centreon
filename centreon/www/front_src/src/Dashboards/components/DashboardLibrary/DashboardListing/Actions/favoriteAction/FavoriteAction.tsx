import {
  IconButton,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import FavoriteIcon from '@mui/icons-material/Favorite';
import { useQueryClient } from '@tanstack/react-query';
import { memo } from 'react';
import {
  dashboardsFavoriteDeleteEndpoint,
  dashboardsFavoriteEndpoit
} from '../../../../../api/endpoints';
import { FavoriteEndpoint, GetLabel } from './models';

const labelDashboardSuccessfullyMarkedAsFavorite =
  'The dashboard successfully marked as favorite';
const labelDashboardSuccessfullyMarkedAsUnFavorite =
  'The dashboard successfully marked as unFavorite';
const labelNotMarkedAsFavorite = 'Not marked as favorite';
const labelMarkedAsFavorite = 'Marked as favorite';

interface Props {
  dashboardId: number;
  isFavorite: boolean;
}

const FavoriteAction = ({ dashboardId, isFavorite }: Props) => {
  const { showSuccessMessage } = useSnackbar();

  const queryClient = useQueryClient();

  const getLabel = ({ setLabel, unsetLabel }: GetLabel) => {
    if (isFavorite) {
      return unsetLabel;
    }
    return setLabel;
  };

  const getEndpoint = (data: FavoriteEndpoint) => {
    if (data?.dashboardId) {
      return dashboardsFavoriteDeleteEndpoint(data.dashboardId);
    }
    return dashboardsFavoriteEndpoit;
  };

  const onSuccess = () => {
    const labelSuccess = getLabel({
      setLabel: labelDashboardSuccessfullyMarkedAsUnFavorite,
      unsetLabel: labelDashboardSuccessfullyMarkedAsFavorite
    });

    showSuccessMessage(labelSuccess);

    queryClient.invalidateQueries({
      queryKey: ['dashboardList'],
      exact: true
    });
  };

  const method = isFavorite ? Method.DELETE : Method.POST;

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint,
    method,
    onSuccess
  });

  const handleFavorites = () => {
    if (isFavorite) {
      mutateAsync({ _meta: { dashboardId } });
      return;
    }
    mutateAsync({
      payload: { dashboard_id: dashboardId }
    });
  };

  const title = getLabel({
    unsetLabel: labelMarkedAsFavorite,
    setLabel: labelNotMarkedAsFavorite
  });

  return (
    <IconButton
      title={title}
      onClick={handleFavorites}
      color={isFavorite ? 'success' : 'default'}
      disabled={isMutating}
      size="small"
    >
      <FavoriteIcon fontSize="small" />
    </IconButton>
  );
};

export default memo(FavoriteAction);
