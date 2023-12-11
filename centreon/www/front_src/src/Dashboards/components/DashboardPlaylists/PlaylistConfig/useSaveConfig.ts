import { append, equals, findIndex, inc, pick, update } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import { useSetAtom } from 'jotai';
import { generatePath } from 'react-router';

import {
  ListingModel,
  Method,
  useMutationQuery,
  useSnackbar
} from '@centreon/ui';

import { playlistEndpoint, playlistsEndpoint } from '../../../api/endpoints';
import { Playlist, PlaylistConfig, PlaylistConfigToAPI } from '../models';
import {
  labelPlaylistCreated,
  labelPlaylistUpdated
} from '../../../translatedLabels';
import {
  askBeforeClosePlaylistConfigAtom,
  playlistConfigInitialValuesAtom
} from '../atoms';
import { DashboardLayout } from '../../../models';
import { router } from '../../../SingleInstancePage/Playlist/utils';

import routeMap from 'www/front_src/src/reactRoutes/routeMap';

const adaptValues = (values: PlaylistConfig): PlaylistConfigToAPI => ({
  dashboards: values.dashboards.map(pick(['id', 'order'])),
  description: values.description || null,
  is_public: values.isPublic,
  name: values.name,
  rotation_time: values.rotationTime
});

const adaptFromAPI = (values: PlaylistConfigToAPI): PlaylistConfig => ({
  dashboards: values.dashboards,
  description: values.description || null,
  isPublic: values.is_public,
  name: values.name,
  rotationTime: values.rotation_time
});

interface UseSaveConfigState {
  saveDashboard: (values: PlaylistConfig, { setSubmitting }) => void;
}

export const useSaveConfig = ({
  playlistId,
  navigateToCreatedPlaylist
}): UseSaveConfigState => {
  const { t } = useTranslation();
  const navigate = router.useNavigate();

  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );
  const setAskBeforeClosePlaylistConfig = useSetAtom(
    askBeforeClosePlaylistConfigAtom
  );

  const { showSuccessMessage } = useSnackbar();

  const queryClient = useQueryClient();
  const { mutateAsync } = useMutationQuery<PlaylistConfigToAPI, { id: number }>(
    {
      getEndpoint: () =>
        playlistId ? playlistEndpoint(playlistId) : playlistsEndpoint,
      method: playlistId ? Method.PUT : Method.POST,
      onError: (
        _,
        __,
        context: { previousPlaylists?: ListingModel<Playlist> }
      ) => {
        if (context?.previousPlaylists) {
          queryClient.setQueryData<ListingModel<Playlist>>(
            ['playlists'],
            context.previousPlaylists
          );
        }
      },
      onMutate: async (
        playlistConfig: PlaylistConfigToAPI
      ): Promise<{ previousPlaylists?: ListingModel<Playlist> }> => {
        setAskBeforeClosePlaylistConfig(false);
        setPlaylistConfigInitialValues(null);

        await queryClient.cancelQueries({ queryKey: ['playlists'] });

        const previousPlaylists = queryClient.getQueryData<
          ListingModel<Playlist>
        >(['playlists']);

        if (previousPlaylists && playlistId) {
          const playlistIndex = findIndex(
            ({ id }) => equals(playlistId, id),
            previousPlaylists.result
          );

          const newPlaylists = update(
            playlistIndex,
            { id: playlistId, ...adaptFromAPI(playlistConfig) },
            previousPlaylists.result
          );
          queryClient.setQueryData(['playlists'], newPlaylists);
        } else if (previousPlaylists) {
          const newPlaylists = {
            meta: {
              ...previousPlaylists.meta,
              total: inc(previousPlaylists.meta.total)
            },
            result: append(
              {
                id: previousPlaylists.result.length,
                ...adaptFromAPI(playlistConfig)
              },
              previousPlaylists.result
            )
          };
          queryClient.setQueryData(['playlists'], newPlaylists);
        }

        return {
          previousPlaylists
        };
      },
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['playlists'] });
        showSuccessMessage(
          playlistId ? t(labelPlaylistUpdated) : t(labelPlaylistCreated)
        );
      }
    }
  );

  const saveDashboard = (values: PlaylistConfig, { setSubmitting }): void => {
    mutateAsync(adaptValues(values))
      .then((newPlaylist) => {
        if (!navigateToCreatedPlaylist) {
          return;
        }

        navigate(
          generatePath(routeMap.dashboard, {
            dashboardId: newPlaylist.id,
            layout: DashboardLayout.Playlist
          })
        );
      })
      .finally(() => setSubmitting(false));
  };

  return {
    saveDashboard
  };
};
