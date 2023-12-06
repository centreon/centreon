import { DashboardLayout } from '../Dashboards/models';

import routeMap from './routeMap';

export interface DeprecatedRoute {
  deprecatedRoute: {
    path: string;
  };
  ignoreWhen?: (props) => boolean;
  newRoute: {
    parameters: Array<{
      defaultValue?: unknown;
      property: string;
    }>;
    path: string;
  };
}

export const deprecatedRoutes: Array<DeprecatedRoute> = [
  {
    deprecatedRoute: {
      path: '/home/dashboards'
    },
    newRoute: {
      parameters: [
        {
          defaultValue: DashboardLayout.Library,
          property: 'layout'
        }
      ],
      path: routeMap.dashboards
    }
  },
  {
    deprecatedRoute: {
      path: '/home/dashboards/:dashboardId'
    },
    ignoreWhen: (pathname: string): boolean =>
      ['/home/dashboards/library', '/home/dashboards/playlists'].includes(
        pathname
      ),
    newRoute: {
      parameters: [
        {
          defaultValue: DashboardLayout.Library,
          property: 'layout'
        },
        {
          property: 'dashboardId'
        }
      ],
      path: routeMap.dashboard
    }
  }
];
