import {
  IconButton,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';
import FavoriteIcon from '@mui/icons-material/Favorite';
import { memo, useRef, useState } from 'react';
import { useTransition } from 'react';
import { useTranslation } from 'react-i18next';

import {
  dashboardsFavoriteDeleteEndpoint,
  dashboardsFavoriteEndpoint
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
  const labelSuccess = useRef('');
  const { showSuccessMessage } = useSnackbar();
  const [isPending, startTransition] = useTransition();
  const [color, setColor] = useState('');
  const [title, setTitle] = useState('');

  const getEndpoint = (data: FavoriteEndpoint) => {
    if (data?.dashboardId) {
      return dashboardsFavoriteDeleteEndpoint(data.dashboardId);
    }
    return dashboardsFavoriteEndpoint;
  };

  const onSuccess = () => {
    showSuccessMessage(labelSuccess.current);
    refetch?.();
  };

  const onError = () => {
    const previousColor = isFavorite ? 'success' : 'default';
    const previousTitle = getLabel({
      setLabel: labelAddToFavorites,
      unsetLabel: labelRemoveFromFavorites,
      asFavorite: isFavorite
    });

    setColor(previousColor);
    setTitle(previousTitle);
  };

  const { mutateAsync } = useMutationQuery({
    getEndpoint,
    method: isFavorite ? Method.DELETE : Method.POST,
    onSuccess,
    fetchHeaders: { 'Content-Type': 'application/json' },
    onError
  });

  const getLabel = ({ setLabel, unsetLabel, asFavorite }: GetLabel) => {
    if (asFavorite) {
      return t(unsetLabel);
    }
    return t(setLabel);
  };

  const handleFavorites = () => {
    const expectedColor = isFavorite ? 'default' : 'success';

    const expectedTitle = getLabel({
      setLabel: labelRemoveFromFavorites,
      unsetLabel: labelAddToFavorites,
      asFavorite: isFavorite
    });
    setTitle(expectedTitle);

    setColor(expectedColor);

    labelSuccess.current = getLabel({
      setLabel: labelDashboardAddedToFavorites,
      unsetLabel: labelDashboardRemovedFromFavorites,
      asFavorite: isFavorite
    });

    startTransition(() => {
      if (isFavorite) {
        mutateAsync({ _meta: { dashboardId } });
        return;
      }
      mutateAsync({
        payload: { dashboard_id: dashboardId }
      });
    });
  };

  const defaultTitle = getLabel({
    setLabel: labelAddToFavorites,
    unsetLabel: labelRemoveFromFavorites,
    asFavorite: isFavorite
  });

  const defaultColor = isFavorite ? 'success' : 'default';

  return (
    <IconButton
      title={title || defaultTitle}
      onClick={handleFavorites}
      color={color || defaultColor}
      disabled={isPending}
      size="small"
      ariaLabel="FavoriteIconButton"
    >
      <FavoriteIcon fontSize="small" />
    </IconButton>
  );
};

export default memo(FavoriteAction);
