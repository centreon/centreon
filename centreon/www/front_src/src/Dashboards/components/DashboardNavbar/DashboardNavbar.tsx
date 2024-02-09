import { useLocation } from 'react-router';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { includes } from 'ramda';

import { Link } from '@mui/material';

import { labelDashboards } from '../../translatedLabels';
import routeMap from '../../../reactRoutes/routeMap';
import FederatedComponent from '../../../components/FederatedComponents';

import { useDashboardNavbarStyles } from './DashboardNavbar.styles';

const DashboardNavbar = (): JSX.Element => {
  const { classes } = useDashboardNavbarStyles();
  const { t } = useTranslation();
  const location = useLocation();

  return (
    <nav className={classes.navbar}>
      <Link
        className={classes.link}
        component={RouterLink}
        data-selected={includes(routeMap.dashboards, location.pathname)}
        to={routeMap.dashboards}
        underline="hover"
      >
        {t(labelDashboards)}
      </Link>
      <FederatedComponent path="/it-edition-extensions/playlists/NavigationLink" />
    </nav>
  );
};

export default DashboardNavbar;
