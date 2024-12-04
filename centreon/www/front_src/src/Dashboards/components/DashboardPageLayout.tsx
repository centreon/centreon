import useFavoriteDashboardListIds from '../api/useFavoriteDashboardListIds';
import { DashboardsOverview } from './DashboardLibrary/DashboardsOverview/DashboardsOverview';

const DashboardPageLayout = (): JSX.Element => {
  const isFetched = useFavoriteDashboardListIds();

  return <DashboardsOverview hasFavoriteDashboardListIdsFetched={isFetched} />;
};

export default DashboardPageLayout;
