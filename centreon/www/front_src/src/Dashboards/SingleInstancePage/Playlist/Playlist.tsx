import { Suspense } from 'react';

import { isNil } from 'ramda';

import { PageHeader, PageLayout } from '@centreon/ui/components';
import { LoadingSkeleton } from '@centreon/ui';

import PlaylistQuickAccess from './components/PlaylistQuickAccess';
import { useGetPlaylist } from './hooks/useGetPlaylist';
import PlaylistBody from './components/PlaylistBody';
import Footer from './components/Footer/Footer';

const Playlist = (): JSX.Element | null => {
  const { playlist } = useGetPlaylist();

  if (isNil(playlist)) {
    return null;
  }

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Menu>
              <PlaylistQuickAccess />
            </PageHeader.Menu>
            <PageHeader.Title
              description={playlist?.description || ''}
              title={playlist?.name || ''}
            />
          </PageHeader.Main>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <Suspense
          fallback={
            <LoadingSkeleton height="20vh" variant="rectangular" width="100%" />
          }
        >
          <PlaylistBody
            dashboards={playlist.dashboards}
            playlistId={playlist.id}
            rotationTime={playlist.rotationTime}
          />
        </Suspense>
      </PageLayout.Body>
      <Footer dashboards={playlist.dashboards} />
    </PageLayout>
  );
};

export default Playlist;
