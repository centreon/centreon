import { useCallback } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@centreon/ui/components';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';
import { useDashboardUserPermissions } from '../DashboardLibrary/DashboardUserPermissions/useDashboardUserPermissions';

import { playlistConfigInitialValuesAtom } from './atoms';
import PlaylistConfig from './PlaylistConfig/PlaylistConfig';
import { initialValue } from './PlaylistConfig/utils';

const DashboardPlaylistsOverview = (): JSX.Element => {
  const { t } = useTranslation();
  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );

  const { canCreateOrManageDashboards } = useDashboardUserPermissions();

  const openConfig = useCallback(() => {
    setPlaylistConfigInitialValues(initialValue);
  }, []);

  return (
    <>
      <DataTable variant="listing">
        <DataTable.EmptyState
          canCreate={canCreateOrManageDashboards}
          labels={{
            actions: { create: t(labelCreateAPlaylist) },
            title: t(labelWelcomeToThePlaylistInterface)
          }}
          onCreate={openConfig}
        />
      </DataTable>
      <PlaylistConfig />
    </>
  );
};

export default DashboardPlaylistsOverview;
