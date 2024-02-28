import routeMap from './routeMap';

export interface DeprecatedRoute {
  deprecatedRoute: {
    path: string;
  };
  ignoreWhen?: (props) => boolean;
  newRoute: {
    parameters?: Array<{
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
          property: 'dashboardId'
        }
      ],
      path: routeMap.dashboard
    }
  }
];
