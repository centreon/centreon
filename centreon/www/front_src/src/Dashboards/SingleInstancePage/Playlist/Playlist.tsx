import { PageHeader, PageLayout } from '@centreon/ui/components';

import PlaylistQuickAccess from './components/PlaylistQuickAccess';
import { useGetPlaylist } from './hooks/useGetPlaylist';

const Playlist = (): JSX.Element => {
  const { playlist } = useGetPlaylist();

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
      <PageLayout.Body>coucou</PageLayout.Body>
    </PageLayout>
  );
};

export default Playlist;
