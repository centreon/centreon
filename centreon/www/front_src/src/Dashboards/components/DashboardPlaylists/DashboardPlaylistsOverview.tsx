import { useCallback } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@centreon/ui/components';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';

import { playlistConfigInitialValuesAtom } from './atoms';
import { Listing } from '../../PlayLists/Listing';

const DashboardPlaylistsOverview = (): JSX.Element => {
  const { t } = useTranslation();
  const setPlaylistConfigInitialValues = useSetAtom(
    playlistConfigInitialValuesAtom
  );

  const openConfig = useCallback(() => {
    setPlaylistConfigInitialValues({
      description: '',
      name: ''
    });
  }, []);

  return (
    <DataTable variant="listing">
      {false ? 
      <DataTable.EmptyState
        canCreate
        labels={{
          actions: { create: t(labelCreateAPlaylist) },
          title: t(labelWelcomeToThePlaylistInterface)
        }}
        onCreate={openConfig}
      /> : 
      <div style={{ height : "100vh", width : "100%"}}>
        <Listing />
      </div>
      }
    </DataTable>
  );
};

export default DashboardPlaylistsOverview;
