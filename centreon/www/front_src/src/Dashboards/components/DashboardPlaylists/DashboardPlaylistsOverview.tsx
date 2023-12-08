import { useCallback } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@centreon/ui/components';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';
import { useDashboardUserPermissions } from '../DashboardLibrary/DashboardUserPermissions/useDashboardUserPermissions';

import { Listing } from './PlaylistsListing';
import { playlistConfigInitialValuesAtom } from './atoms';
import { useDashboardPlaylistsOverview } from './useDashboardPlaylistsOverview';
import PlaylistConfig from './PlaylistConfig/PlaylistConfig';

const DashboardPlaylistsOverview = (): JSX.Element => {
  const { t } = useTranslation();
  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );
  const { isEmptyList, loading, data } = useDashboardPlaylistsOverview();

  const openConfig = useCallback(() => {
    setPlaylistConfigInitialValues({
      dashboards: [],
      description: '',
      isPublic: false,
      name: '',
      rotationTime: 10
    });
  }, []);

  const { canCreateOrManageDashboards } = useDashboardUserPermissions();

  return (
    <>
    <DataTable variant="listing">
      {isEmptyList ? (
        <DataTable.EmptyState
          canCreate={canCreateOrManageDashboards}
          labels={{
            actions: { create: t(labelCreateAPlaylist) },
            title: t(labelWelcomeToThePlaylistInterface)
          }}
          onCreate={openConfig}
        />
      ) : (
        <div style={{ minHeight: '75vh', minWidth: '100%' }}>
          <Listing data={data} loading={loading} openConfig={openConfig} />
        </div>
      )}
    </DataTable>
    <PlaylistConfig />
    </>
  );
};

export default DashboardPlaylistsOverview;
