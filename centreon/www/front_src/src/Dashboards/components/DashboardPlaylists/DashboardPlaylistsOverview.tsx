import { useCallback } from 'react';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { DataTable } from '@centreon/ui/components';

import {
  labelCreateAPlaylist,
  labelWelcomeToThePlaylistInterface
} from '../../translatedLabels';

import { playlistConfigInitialValuesAtom } from './atoms';

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
      <DataTable.EmptyState
        canCreate
        labels={{
          actions: { create: t(labelCreateAPlaylist) },
          title: t(labelWelcomeToThePlaylistInterface)
        }}
        onCreate={openConfig}
      />
    </DataTable>
  );
};

export default DashboardPlaylistsOverview;
