import getBreadcrumbsByPath from '.';

describe('breadcrumbSelector', () => {
  it('returns formatted breadcrumbs', () => {
    const navigation = {
      result: [
        {
          children: [
            {
              groups: [],
              is_react: true,
              label: 'Custom Views',
              options: null,
              page: '103',
              url: '/home/customViews'
            }
          ],
          is_react: false,
          label: 'Home',
          options: null,
          page: '1',
          url: './include/home/home.php'
        }
      ]
    };
    const breadcrumbs = getBreadcrumbsByPath(navigation.result);

    expect(breadcrumbs).toEqual({
      '/home/customViews': [
        {
          is_react: false,
          label: 'Home',
          link: '/main.php?p=1',
          options: null
        },
        {
          is_react: true,
          label: 'Custom Views',
          link: '/home/customViews',
          options: null
        }
      ],
      '/main.php?p=1': [
        { is_react: false, label: 'Home', link: '/main.php?p=1', options: null }
      ]
    });
  });

  it('returns first url found in parent level', () => {
    const navigation = {
      result: [
        {
          children: [
            {
              groups: [
                {
                  children: [
                    {
                      is_react: true,
                      label: 'Hosts',
                      options: null,
                      page: '20101',
                      url: '/configuration/hosts'
                    }
                  ],
                  label: 'hosts'
                }
              ],
              is_react: false,
              label: 'Hosts',
              options: null,
              page: '201',
              url: null
            },
            {
              groups: [
                {
                  children: [
                    {
                      is_react: true,
                      label: 'Services',
                      options: null,
                      page: '20102',
                      url: '/configuration/services'
                    }
                  ],
                  label: 'services'
                }
              ],
              is_react: false,
              label: 'Services',
              options: null,
              page: '202',
              url: null
            }
          ],
          is_react: false,
          label: 'Configuration',
          options: null,
          page: '2',
          url: './include/home/home.php'
        }
      ]
    };
    const breadcrumbs = getBreadcrumbsByPath(navigation.result);

    expect(breadcrumbs).toEqual({
      '/configuration/hosts': [
        {
          is_react: false,
          label: 'Configuration',
          link: '/main.php?p=2',
          options: null
        },
        {
          is_react: false,
          label: 'Hosts',
          link: '/configuration/hosts',
          options: null
        },
        {
          is_react: true,
          label: 'Hosts',
          link: '/configuration/hosts',
          options: null
        }
      ],
      '/configuration/services': [
        {
          is_react: false,
          label: 'Configuration',
          link: '/main.php?p=2',
          options: null
        },
        {
          is_react: false,
          label: 'Services',
          link: '/configuration/services',
          options: null
        },
        {
          is_react: true,
          label: 'Services',
          link: '/configuration/services',
          options: null
        }
      ],
      '/main.php?p=2': [
        {
          is_react: false,
          label: 'Configuration',
          link: '/main.php?p=2',
          options: null
        }
      ]
    });
  });
});
