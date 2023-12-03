import { useCallback } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@centreon/ui/components';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';
import { Listing } from './PlaylistsListing';

import { playlistConfigInitialValuesAtom } from './atoms';
import { useDashboardUserPermissions } from '../DashboardLibrary/DashboardUserPermissions/useDashboardUserPermissions';
import { useDashboardPlaylistsOverview } from './useDashboardPlaylistsOverview';

const DashboardPlaylistsOverview = (): JSX.Element => {
  const { t } = useTranslation();
  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );
  const {isEmptyList , loading , data} = useDashboardPlaylistsOverview()


  const openConfig = useCallback(() => {
    setPlaylistConfigInitialValues({
      dashboards: [],
      description: '',
      isPublic: false,
      name: '',
      rotationTime: 10
    });
  }, []);

  const { canCreateOrManageDashboards } =
  useDashboardUserPermissions(); // can replaced with playlist version


  return (
    <DataTable variant="listing">
      {/* handle empty data case */}
      {isEmptyList  ? (
        <DataTable.EmptyState
          canCreate = {canCreateOrManageDashboards}
          labels={{
              actions: { create: t(labelCreateAPlaylist) },
              title: t(labelWelcomeToThePlaylistInterface)
            }}
          onCreate={openConfig}
        />
      ) : (
        <div style={{ height: '100vh', width: '100%' }}>
          <Listing loading = {loading} data = {data} />
        </div>
      )}
    </DataTable>
  );
};

export default DashboardPlaylistsOverview;
