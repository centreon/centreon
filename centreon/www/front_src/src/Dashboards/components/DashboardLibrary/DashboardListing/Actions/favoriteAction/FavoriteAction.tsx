import {
  IconButton,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import FavoriteIcon from '@mui/icons-material/Favorite';
import { memo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import {
  dashboardsFavoriteDeleteEndpoint,
  dashboardsFavoriteEndpoit
} from '../../../../../api/endpoints';
import {
  labelAddToFavorites,
  labelDashboardAddedToFavorites,
  labelDashboardRemovedFromFavorites,
  labelRemoveFromFavorites
} from '../../../../../translatedLabels';
import { FavoriteEndpoint, GetLabel } from './models';

interface Props {
  dashboardId: number;
  isFavorite: boolean;
  refetch?: () => void;
}

const FavoriteAction = ({ dashboardId, isFavorite, refetch }: Props) => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const labelSuccess = useRef('');
  const method = useRef(Method.POST);

  const getLabel = ({ setLabel, unsetLabel, asFavorite }: GetLabel) => {
    if (asFavorite) {
      return t(unsetLabel);
    }
    return t(setLabel);
  };

  const getEndpoint = (data: FavoriteEndpoint) => {
    if (data?.dashboardId) {
      return dashboardsFavoriteDeleteEndpoint(data.dashboardId);
    }
    return dashboardsFavoriteEndpoit;
  };

  const onSuccess = () => {
    showSuccessMessage(labelSuccess.current);
    refetch?.();
  };

  const { mutateAsync, isMutating } = useMutationQuery({
    getEndpoint,
    method: method.current,
    onSuccess
  });

  const handleFavorites = () => {
    labelSuccess.current = getLabel({
      setLabel: labelDashboardAddedToFavorites,
      unsetLabel: labelDashboardRemovedFromFavorites,
      asFavorite: isFavorite
    });

    method.current = isFavorite ? Method.DELETE : Method.POST;

    if (isFavorite) {
      mutateAsync({ _meta: { dashboardId } });
      return;
    }
    mutateAsync({
      payload: { dashboard_id: dashboardId }
    });
  };

  const title = getLabel({
    setLabel: labelAddToFavorites,
    unsetLabel: labelRemoveFromFavorites,
    asFavorite: isFavorite
  });

  return (
    <IconButton
      title={title}
      onClick={handleFavorites}
      color={isFavorite ? 'success' : 'default'}
      disabled={isMutating}
      size="small"
      ariaLabel="FavoriteIconButton"
    >
      <FavoriteIcon fontSize="small" />
    </IconButton>
  );
};

export default memo(FavoriteAction);
