import { useAtom } from 'jotai';

import { Modal } from '@centreon/ui/components';

import FederatedComponent from '../../../../components/FederatedComponents';
import { dashboardToAddToPlaylistAtom } from '../../../atoms';

const AddToPlaylistModal = (): JSX.Element => {
  const [dashboardToAddToPlaylist, setDashboardToAddToPlaylist] = useAtom(
    dashboardToAddToPlaylistAtom
  );

  const close = (): void => {
    setDashboardToAddToPlaylist(null);
  };

  return (
    <Modal hasCloseButton={false} open={Boolean(dashboardToAddToPlaylist)}>
      <FederatedComponent
        close={close}
        dashboardId={dashboardToAddToPlaylist?.id}
        path="/it-edition-extensions/playlists/AddDashboardToPlaylist"
      />
    </Modal>
  );
};

export default AddToPlaylistModal;
