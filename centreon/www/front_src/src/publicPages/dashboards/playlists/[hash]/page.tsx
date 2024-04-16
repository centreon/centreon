import FederatedPage from '../../../../components/FederatedPage/FederatedPage';

const PlaylistPage = (): JSX.Element => {
  return (
    <FederatedPage
      childrenComponent="DashboardLayout"
      route="/home/dashboards/playlists/:id"
    />
  );
};

export default PlaylistPage;
