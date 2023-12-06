import { generatePath } from 'react-router';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';
import { Link as RouterLink } from 'react-router-dom';

import { Link } from '@mui/material';

import { DashboardLayout } from '../../models';
import { labelDashboardLibrary, labelPlaylists } from '../../translatedLabels';
import routeMap from '../../../reactRoutes/routeMap';
import { routerHooks } from '../../routerHooks';

import { useDashboardNavbarStyles } from './DashboardNavbar.styles';

const links = [
  {
    label: labelDashboardLibrary,
    layout: DashboardLayout.Library
  },
  {
    label: labelPlaylists,
    layout: DashboardLayout.Playlist
  }
];

const DashboardNavbar = (): JSX.Element => {
  const { classes } = useDashboardNavbarStyles();
  const { t } = useTranslation();
  const { layout } = routerHooks.useParams();

  return (
    <nav className={classes.navbar}>
      {links.map(({ layout: linkLayout, label }) => (
        <Link
          className={classes.link}
          component={RouterLink}
          data-selected={equals(layout, linkLayout)}
          key={label}
          to={generatePath(routeMap.dashboards, { layout: linkLayout })}
          underline="hover"
        >
          {t(label)}
        </Link>
      ))}
    </nav>
  );
};

export default DashboardNavbar;
