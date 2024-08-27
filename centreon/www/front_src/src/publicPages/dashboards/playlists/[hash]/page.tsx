import FederatedPage from '../../../../components/FederatedPage/FederatedPage';
import { ComponentProps } from '../../../models';

const PlaylistPage = (props: ComponentProps): JSX.Element => {
  return (
    <FederatedPage
      childrenComponent="DashboardLayout"
      route="/home/dashboards/playlists/:id"
      {...props}
    />
  );
};

export default PlaylistPage;
