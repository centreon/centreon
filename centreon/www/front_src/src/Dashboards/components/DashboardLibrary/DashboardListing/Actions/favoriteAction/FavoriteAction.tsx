import FavoriteIcon from '@mui/icons-material/Favorite';

import {
  IconButton,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { memo, useRef, useState, useTransition } from 'react';
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
  isFetching: boolean;
}

const FavoriteAction = ({
  dashboardId,
  isFavorite,
  refetch,
  isFetching
}: Props) => {
  const { t } = useTranslation();
  const labelSuccess = useRef('');
  const { showSuccessMessage } = useSnackbar();
  const [, startTransition] = useTransition();
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
      asFavorite: isFavorite,
      setLabel: labelAddToFavorites,
      unsetLabel: labelRemoveFromFavorites
    });

    setColor(previousColor);
    setTitle(previousTitle);
  };

  const { mutateAsync, isMutating } = useMutationQuery({
    fetchHeaders: { 'Content-Type': 'application/json' },
    getEndpoint,
    method: isFavorite ? Method.DELETE : Method.POST,
    onError,
    onSuccess
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
      asFavorite: isFavorite,
      setLabel: labelRemoveFromFavorites,
      unsetLabel: labelAddToFavorites
    });
    setTitle(expectedTitle);

    setColor(expectedColor);

    labelSuccess.current = getLabel({
      asFavorite: isFavorite,
      setLabel: labelDashboardAddedToFavorites,
      unsetLabel: labelDashboardRemovedFromFavorites
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
    asFavorite: isFavorite,
    setLabel: labelAddToFavorites,
    unsetLabel: labelRemoveFromFavorites
  });

  const defaultColor = isFavorite ? 'success' : 'default';

  return (
    <IconButton
      ariaLabel="FavoriteIconButton"
      color={color || defaultColor}
      disabled={isFetching || isMutating}
      onClick={handleFavorites}
      size="small"
      title={title || defaultTitle}
    >
      <FavoriteIcon fontSize="small" />
    </IconButton>
  );
};

export default memo(FavoriteAction);
