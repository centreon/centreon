import { adaptNavigation, defaultGroupLabel } from './adaptNavigation';
import { getSelectedRoute } from './getSelectedRoute';
import icons from './icons';
import type { Page } from './models';

const legacyPage = (page: string, label: string, show = true): Page => ({
  is_react: false,
  label,
  options: null,
  page,
  show,
  url: './include/page.php'
});

const pages: Array<Page> = [
  {
    children: [
      {
        groups: [],
        is_react: true,
        label: 'Resources Status',
        options: null,
        page: '200',
        show: true,
        url: '/monitoring/resources'
      },
      {
        groups: [
          {
            children: [
              { ...legacyPage('20201', 'Services'), options: '&o=svc' }
            ],
            label: defaultGroupLabel
          },
          {
            children: [
              legacyPage('20204', 'Services Grid'),
              legacyPage('20299', 'Hidden details', false)
            ],
            label: 'By Status'
          },
          {
            children: [legacyPage('20298', 'Only hidden', false)],
            label: 'Empty'
          }
        ],
        is_react: false,
        label: 'Status Details',
        options: null,
        page: '202',
        show: true,
        url: null
      },
      {
        groups: [],
        is_react: true,
        label: 'Poller Wizard',
        options: null,
        page: '203',
        show: false,
        url: '/poller-wizard/1'
      }
    ],
    icon: 'monitoring',
    is_react: false,
    label: 'Monitoring',
    page: '2',
    show: true,
    url: null
  },
  {
    children: [
      {
        groups: [],
        is_react: true,
        label: 'Dashboards',
        options: 'beta',
        page: '104',
        show: true,
        url: '/home/dashboards'
      }
    ],
    icon: 'home',
    is_react: false,
    label: 'Home',
    page: '1',
    show: true,
    url: null
  },
  {
    children: [],
    icon: 'reporting',
    is_react: true,
    label: 'Not reachable',
    page: '3',
    show: true,
    url: null
  }
];

describe('adaptNavigation', () => {
  it('maps the topology tree to the menu hierarchy', () => {
    expect(adaptNavigation(pages)).toEqual([
      {
        Icon: icons.monitoring,
        label: 'Monitoring',
        nonDisplayedRoutes: ['/poller-wizard/1'],
        route: '/monitoring/resources',
        subRoutes: [
          { label: 'Resources Status', route: '/monitoring/resources' },
          {
            groups: [
              {
                label: undefined,
                subRoutes: [
                  { label: 'Services', route: '/main.php?p=20201&o=svc' }
                ]
              },
              {
                label: 'By Status',
                subRoutes: [
                  { label: 'Services Grid', route: '/main.php?p=20204' }
                ]
              }
            ],
            label: 'Status Details',
            nonDisplayedRoutes: ['/main.php?p=20299', '/main.php?p=20298'],
            route: '/main.php?p=20201&o=svc'
          }
        ]
      },
      {
        Icon: icons.home,
        label: 'Home',
        route: '/home/dashboards',
        subRoutes: [
          { badge: 'beta', label: 'Dashboards', route: '/home/dashboards' }
        ]
      }
    ]);
  });

  it('translates the labels, the group headers and the badges', () => {
    const [monitoring, home] = adaptNavigation(
      pages,
      (label) => `translated ${label}`
    );

    expect(monitoring.label).toBe('translated Monitoring');
    expect(monitoring.subRoutes?.[1].groups?.[1].label).toBe(
      'translated By Status'
    );
    expect(monitoring.subRoutes?.[1].groups?.[0].label).toBeUndefined();
    expect(home.subRoutes?.[0].badge).toBe('translated beta');
  });
});

describe('getSelectedRoute', () => {
  const routes = adaptNavigation(pages);

  it.each([
    [
      'a React page by its path',
      '/monitoring/resources',
      '',
      '/monitoring/resources'
    ],
    [
      'a React page nested under a menu path',
      '/home/dashboards/library/1',
      '',
      '/home/dashboards'
    ],
    ['a non-displayed React page', '/poller-wizard/1', '', '/poller-wizard/1'],
    [
      'a legacy page by its page number',
      '/main.php',
      '?p=20201&o=svc&id=3',
      '/main.php?p=20201&o=svc'
    ],
    [
      'a non-displayed legacy page',
      '/main.php',
      '?p=20299',
      '/main.php?p=20299'
    ],
    ['nothing for an unknown legacy page', '/main.php', '?p=99999', undefined],
    ['nothing for a legacy URL without page', '/main.php', '', undefined],
    ['nothing for an unknown React page', '/unknown', '', undefined]
  ])('finds %s', (_, pathname, search, expectedRoute) => {
    expect(getSelectedRoute({ pathname, routes, search })).toBe(expectedRoute);
  });
});
