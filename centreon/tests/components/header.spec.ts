import type { Locator, Page } from '@playwright/test';
import { mergeDeepRight } from 'ramda';

import {
  labelAllPollers,
  labelCancel,
  labelConfigurationExportedAndReloaded,
  labelConfigurePollers,
  labelDatabaseNotActive,
  labelDatabaseUpdateAndActive,
  labelDatabaseUpdatesNotActive,
  labelExportAndReload,
  labelExportAndReloadTheConfiguration,
  labelExportConfiguration,
  labelExportingAndReloadingTheConfiguration,
  labelLatencyDetected,
  labelNoLatencyDetected,
  labelPollerNotRunning,
  labelPollers
} from '../../www/front_src/src/Header/Poller/translatedLabels';
import {
  labelDownStatusHosts,
  labelHosts,
  labelPendingStatusHosts,
  labelUnreachableStatusHosts,
  labelUpStatusHosts
} from '../../www/front_src/src/Header/Resources/Host/translatedLabels';
import {
  labelCriticalStatusServices,
  labelOkStatusServices,
  labelPendingStatusServices,
  labelServices,
  labelUnknownStatusServices,
  labelWarningStatusServices
} from '../../www/front_src/src/Header/Resources/Service/translatedLabels';
import {
  labelCopyAutologinLink,
  labelEditProfile,
  labelFullscreen,
  labelLogout
} from '../../www/front_src/src/Header/UserMenu/translatedLabels';
import { retrievedNavigation } from '../../www/front_src/src/Navigation/mocks';
import { expect, type MockApi, test } from './fixtures';

// Migrated from Header/specs (Header, hosts, services, pollers and user menu).
const storyId = 'Header/Header/Default';
// Header/Poller/getPollerPropsAdapter, which Node cannot import (@centreon/ui).
const pollerConfigurationPageNumber = '60901';

// The API v1 answers of Header/specs/Header.utils, merged with each test's.
const defaultStubs = {
  hosts_status: {
    down: { total: '43', unhandled: '34' },
    ok: '12134',
    pending: '10',
    refreshTime: 1000000,
    time: 1252285255,
    total: 1605,
    unreachable: { total: '0', unhandled: '0' }
  },
  navigationList: retrievedNavigation,
  pollersListIssues: {
    issues: {
      database: {
        critical: {
          poller: [{ id: 125, name: 'poller 125', since: '24/12/2019' }],
          total: 0
        },
        total: 0,
        warning: {
          poller: [{ id: 145, name: 'poller 145', since: '24/08/2022' }],
          total: 0
        }
      },
      latency: {
        critical: {
          poller: [{ id: 175, name: 'poller 175', since: '24/07/2021' }],
          total: 0
        },
        total: 0,
        warning: {
          poller: [{ id: 789, name: 'poller 789', since: '24/02/2023' }],
          total: 0
        }
      }
    },
    refreshTime: 1000000,
    total: 12
  },
  servicesStatus: {
    critical: { total: '43', unhandled: '34' },
    ok: '12134',
    pending: '1000000',
    refreshTime: 90,
    time: 1252285255,
    total: 1605,
    unknown: { total: '43', unhandled: '34' },
    warning: { total: '43', unhandled: '34' }
  },
  user: {
    autologinkey: 'LKEY-autologin',
    fullname: 'admin admin',
    hasAccessToProfile: true,
    isExportButtonEnabled: false,
    locale: 'en_US.UTF-8',
    password_remaining_time: 15369910,
    soundNotificationsEnabled: true,
    timezone: 'Europe/Paris',
    userId: '1',
    username: 'admin'
  }
};

interface SetUp {
  installedModules?: Array<string>;
  isExportButtonEnabled?: boolean;
  navigationList?: object;
  stubs?: object;
}

const setUp = async (
  {
    mockApi,
    mount,
    page
  }: {
    mockApi: MockApi;
    mount: (id: string, props?: object) => Promise<Locator>;
    page: Page;
  },
  { stubs = {}, ...props }: SetUp = {}
) => {
  const data = mergeDeepRight(defaultStubs, stubs) as Record<string, unknown>;
  await mockApi({
    endpoint: './api/internal.php',
    response: (url: URL) => data[url.searchParams.get('action') ?? ''] ?? {}
  });
  await mockApi({
    endpoint:
      './api/latest/configuration/monitoring-servers/generate-and-reload',
    response: {}
  });
  await mockApi({
    endpoint: './api/latest/configuration/users/current/parameters',
    method: 'PATCH',
    response: { theme: 'dark' }
  });
  await page.clock.setFixedTime(new Date('2022-04-28T16:20:00Z'));

  return mount(storyId, props);
};

const location = (page: Page) => page.getByTestId('location');
const expectLocation = (page: Page, expected: string) =>
  expect
    .poll(async () => decodeURIComponent(await location(page).inputValue()))
    .toContain(decodeURIComponent(expected));
const link = (component: Locator, name: string) =>
  component.getByRole('link', { exact: true, name });
const pollerButton = (component: Locator) =>
  component.getByRole('button', { exact: true, name: labelPollers });
const subMenu = (page: Page) => page.locator(`[id="${labelPollers}-menu"]`);

const expectSubMenuClosed = async (component: Locator) => {
  await expect(pollerButton(component)).toHaveAttribute(
    'aria-expanded',
    'false'
  );
  await expect(
    pollerButton(component).getByTestId('ExpandMoreIcon')
  ).toBeVisible();
  await expect(subMenu(component.page())).toBeHidden();
};
const openSubMenu = async (component: Locator) => {
  await pollerButton(component).click();
  await expect(pollerButton(component)).toHaveAttribute(
    'aria-expanded',
    'true'
  );
  await expect(
    pollerButton(component).getByTestId('ExpandLessIcon')
  ).toBeVisible();
  await expect(subMenu(component.page())).toBeVisible();
};

test.use({ viewport: { height: 300, width: 1200 } });

test.describe('layout', () => {
  for (const width of [1024, 768, 599]) {
    test(`renders the top menu items ${width}px wide`, async ({
      expectThemedScreenshot,
      mockApi,
      mount,
      page
    }) => {
      await page.setViewportSize({ height: 300, width });
      const component = await setUp({ mockApi, mount, page });

      await expect(link(component, 'Services')).toBeVisible();
      await expect(link(component, 'Hosts')).toBeVisible();
      await expect(pollerButton(component)).toBeVisible();
      await expect(component.getByLabel('Profile')).toBeVisible();
      await expectThemedScreenshot(component, `header-${width}`);
    });
  }
});

test.describe('pollers', () => {
  const indicators = (component: Locator) => ({
    database: component.getByLabel('database', { exact: true }),
    latency: component.getByLabel('latency', { exact: true })
  });

  test('shows the icon and the chevron, no label', async ({
    mockApi,
    mount,
    page
  }) => {
    await page.setViewportSize({ height: 300, width: 1024 });
    const component = await setUp({ mockApi, mount, page });

    await expect(pollerButton(component).getByText('Pollers')).toHaveCount(0);
    await expect(
      pollerButton(component).getByTestId('ExpandMoreIcon')
    ).toBeVisible();
    await expect(
      pollerButton(component).getByTestId('DeviceHubIcon')
    ).toBeVisible();
  });

  test('hides the indicators on very small screens', async ({
    mockApi,
    mount,
    page
  }) => {
    await page.setViewportSize({ height: 300, width: 599 });
    const component = await setUp({ mockApi, mount, page });

    await expect(pollerButton(component)).toBeVisible();
    await expect(
      component.getByRole('status', { name: 'database' })
    ).toHaveCount(0);
    await expect(
      component.getByRole('status', { name: 'latency' })
    ).toHaveCount(0);
  });

  const indicatorCases: Array<[string, object, string, string]> = [
    [
      'no issue',
      { issues: [] },
      labelDatabaseUpdateAndActive,
      labelNoLatencyDetected
    ],
    [
      'a critical database issue',
      { issues: { database: { critical: { total: 1 } } } },
      labelDatabaseNotActive,
      labelNoLatencyDetected
    ],
    [
      'a database without issues',
      {
        issues: { database: { critical: { total: 0 }, warning: { total: 0 } } }
      },
      labelDatabaseUpdateAndActive,
      labelNoLatencyDetected
    ],
    [
      'a critical latency issue',
      { issues: { latency: { critical: { total: 1 } } } },
      labelDatabaseUpdateAndActive,
      labelLatencyDetected
    ],
    [
      'a latency without issues',
      {
        issues: { latency: { critical: { total: 0 }, warning: { total: 0 } } }
      },
      labelDatabaseUpdateAndActive,
      labelNoLatencyDetected
    ],
    [
      'stability issues only',
      {
        issues: {
          stability: {
            critical: {
              poller: [{ id: 1, name: 'poller1', since: '' }],
              total: 1
            },
            total: 1
          }
        }
      },
      labelDatabaseUpdateAndActive,
      labelNoLatencyDetected
    ],
    [
      'no stability issue',
      {
        issues: {
          stability: {
            critical: { poller: [], total: 0 },
            total: 0,
            warning: { poller: [], total: 0 }
          }
        }
      },
      labelDatabaseUpdateAndActive,
      labelNoLatencyDetected
    ]
  ];

  for (const [name, pollersListIssues, database, latency] of indicatorCases) {
    test(`shows the indicators for ${name}`, async ({
      mockApi,
      mount,
      page
    }) => {
      const component = await setUp(
        { mockApi, mount, page },
        { stubs: { pollersListIssues } }
      );

      await expect(indicators(component).database).toContainText(database);
      await expect(indicators(component).latency).toContainText(latency);
    });
  }

  test('opens and closes the sub menu', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount, page });
    await expectSubMenuClosed(component);

    await openSubMenu(component);
    await expect(subMenu(page).locator('li').first()).toContainText(
      labelAllPollers
    );
    await expect(subMenu(page).locator('li').first()).toContainText('12');
    await expectThemedScreenshot(page, 'header-pollers-sub-menu');

    await page.keyboard.press('Escape');
    await expectSubMenuClosed(component);
    await openSubMenu(component);
    await page.mouse.click(5, 290);
    await expectSubMenuClosed(component);
    await openSubMenu(component);
    await pollerButton(component).click();
    await expectSubMenuClosed(component);
  });

  test('hides the total when there are issues', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount, page },
      { stubs: { pollersListIssues: { issues: { latency: { total: 1 } } } } }
    );
    await openSubMenu(component);

    await expect(subMenu(page).locator('li').first()).not.toContainText(
      labelAllPollers
    );
  });

  test('lists the issues with their counts', async ({
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp(
      { mockApi, mount, page },
      {
        stubs: {
          pollersListIssues: {
            issues: {
              database: { total: 1 },
              latency: { total: 1 },
              stability: { total: 1 }
            }
          }
        }
      }
    );
    await openSubMenu(component);

    const items = page.getByTestId('pollerIssues');
    await expect(items).toHaveCount(3);
    for (const [index, text] of [
      labelDatabaseUpdatesNotActive,
      labelLatencyDetected,
      labelPollerNotRunning
    ].entries()) {
      await expect(items.nth(index)).toContainText(text);
      await expect(items.nth(index)).toContainText('1');
    }
  });

  test.describe('configuration', () => {
    const allowedNavigation = {
      result: [
        {
          children: [
            {
              groups: [],
              is_react: false,
              label: 'Resources Status',
              options: null,
              page: pollerConfigurationPageNumber,
              show: true,
              url: '/config/'
            }
          ]
        }
      ]
    };

    test('hides the configuration button without access to its page', async ({
      mockApi,
      mount,
      page
    }) => {
      const component = await setUp(
        { mockApi, mount, page },
        { navigationList: { result: [] } }
      );
      await openSubMenu(component);

      await expect(
        page.getByRole('button', { name: labelConfigurePollers })
      ).toHaveCount(0);
    });

    test('opens the configuration page and closes the sub menu', async ({
      mockApi,
      mount,
      page
    }) => {
      const component = await setUp(
        { mockApi, mount, page },
        { navigationList: allowedNavigation }
      );
      await openSubMenu(component);
      const configure = page.getByTestId(labelConfigurePollers);
      await expect(configure).toBeVisible();
      await configure.click();

      await expectLocation(page, `main.php?p=${pollerConfigurationPageNumber}`);
      await expect(configure).toBeHidden();
    });

    test.describe('when the export is allowed', () => {
      const props = { isExportButtonEnabled: true };

      test('opens the export modal and closes it on cancel', async ({
        mockApi,
        mount,
        page
      }) => {
        const component = await setUp({ mockApi, mount, page }, props);
        await openSubMenu(component);
        await page.getByTestId(labelExportConfiguration).click();

        const dialog = page.getByRole('dialog');
        await expect(
          dialog.getByRole('heading', {
            name: labelExportAndReloadTheConfiguration
          })
        ).toBeVisible();
        await dialog.getByRole('button', { name: labelCancel }).click();
        await expect(dialog).toHaveCount(0);
      });

      test('exports the configuration', async ({ mockApi, mount, page }) => {
        const component = await setUp({ mockApi, mount, page }, props);
        await openSubMenu(component);
        await page.getByTestId(labelExportConfiguration).click();
        await page
          .getByRole('dialog')
          .getByRole('button', { name: labelExportAndReload })
          .click();

        const alerts = page.getByRole('alert');
        await expect(alerts).toHaveCount(2);
        await expect(alerts.nth(0)).toContainText(
          labelExportingAndReloadingTheConfiguration
        );
        await expect(alerts.nth(1)).toContainText(
          labelConfigurationExportedAndReloaded
        );
        await expect(page.getByRole('dialog')).toHaveCount(0);
        await expectSubMenuClosed(component);
      });
    });
  });
});

const resourceCases = [
  {
    counters: [
      [labelDownStatusHosts, '12', 'DOWN', 'Down', true],
      [labelUnreachableStatusHosts, '126', 'UNREACHABLE', 'Unreachable', true],
      [labelUpStatusHosts, '12.1k', 'UP', 'Up', false],
      [labelPendingStatusHosts, '7', 'PENDING', 'Pending', false]
    ],
    icon: 'HostIcon',
    label: labelHosts,
    resourceTypes: '[{"id":"host","name":"Host"}]',
    stubs: {
      hosts_status: {
        down: { unhandled: '12' },
        ok: '12134',
        pending: '7',
        unreachable: { unhandled: '126' }
      }
    }
  },
  {
    counters: [
      [labelCriticalStatusServices, '12', 'CRITICAL', 'Critical', true],
      [labelUnknownStatusServices, '126', 'UNKNOWN', 'Unknown', true],
      [labelOkStatusServices, '12.1k', 'OK', 'Ok', false],
      [labelWarningStatusServices, '14.7m', 'WARNING', 'Warning', true],
      [labelPendingStatusServices, '3', 'PENDING', 'Pending', false]
    ],
    icon: 'GrainIcon',
    label: labelServices,
    resourceTypes:
      '[{"id":"service","name":"Service"},{"id":"metaservice","name":"Meta-Service"}]',
    stubs: {
      servicesStatus: {
        critical: { unhandled: '12' },
        ok: '12134',
        pending: '3',
        unknown: { unhandled: '126' },
        warning: { unhandled: '14688222' }
      }
    }
  }
] as const;

const resourcesLink = (
  resourceTypes: string,
  status: string,
  name: string,
  unhandled: boolean
) =>
  `monitoring/resources?filter={"criterias":[{"name":"resource_types","value":${resourceTypes}},{"name":"statuses","value":[{"id":"${status}","name":"${name}"}]},{"name":"states","value":[${
    unhandled ? '{"id":"unhandled_problems","name":"Unhandled"}' : ''
  }]},{"name":"search","value":""}]}&fromTopCounter=true`;

for (const { counters, icon, label, resourceTypes, stubs } of resourceCases) {
  test.describe(label, () => {
    test('shows the icon without a chevron', async ({
      mockApi,
      mount,
      page
    }) => {
      await page.setViewportSize({ height: 300, width: 1024 });
      const component = await setUp({ mockApi, mount, page });
      const resourceLink = link(component, label);

      await expect(resourceLink.getByTestId(icon)).toBeVisible();
      await expect(resourceLink.getByTestId('ExpandMoreIcon')).toHaveCount(0);
    });

    test('hides the counters under 600px', async ({ mockApi, mount, page }) => {
      await page.setViewportSize({ height: 300, width: 599 });
      const component = await setUp({ mockApi, mount, page });

      await expect(link(component, label)).toBeVisible();
      for (const [counter] of counters) {
        await expect(link(component, counter)).toHaveCount(0);
      }
    });

    test('formats the counters and links them to the resource status page', async ({
      expectThemedScreenshot,
      mockApi,
      mount,
      page
    }) => {
      const component = await setUp({ mockApi, mount, page }, { stubs });

      for (const [counter, value] of counters) {
        await expect(link(component, counter)).toBeVisible();
        await expect(link(component, counter)).toContainText(value);
      }
      await expectThemedScreenshot(
        component,
        `header-${label.toLowerCase()}-counters`
      );

      for (const [counter, , status, name, unhandled] of counters) {
        await link(component, counter).click();
        await expectLocation(
          page,
          resourcesLink(resourceTypes, status, name, unhandled)
        );
      }
    });
  });
}

test('adds anomaly detection to the service redirection when installed', async ({
  mockApi,
  mount,
  page
}) => {
  const component = await setUp(
    { mockApi, mount, page },
    { installedModules: ['centreon-anomaly-detection'] }
  );
  await link(component, labelCriticalStatusServices).click();

  await expectLocation(
    page,
    resourcesLink(
      '[{"id":"service","name":"Service"},{"id":"metaservice","name":"Meta-Service"},{"id":"anomaly-detection","name":"Anomaly detection"}]',
      'CRITICAL',
      'Critical',
      true
    )
  );
});

test.describe('user menu', () => {
  const userIcon = (component: Locator) =>
    component.locator('[data-cy=userIcon]');
  const openUserMenu = async (component: Locator) => {
    await userIcon(component).click();
    const popper = component.page().locator('[data-cy=popper]');
    await expect(popper).toBeVisible();

    return popper;
  };

  test('shows the clock', async ({ mockApi, mount, page }) => {
    const component = await setUp({ mockApi, mount, page });
    const clock = component.locator('[data-cy=clock]');

    await expect(clock).toContainText('April 28, 2022');
    await expect(clock).toContainText('4:20 PM');
  });

  test('hides the clock under 769px', async ({ mockApi, mount, page }) => {
    await page.setViewportSize({ height: 500, width: 768 });
    const component = await setUp({ mockApi, mount, page });

    await expect(component.locator('[data-cy=clock]')).toBeHidden();
  });

  test('expands the user menu from the user icon, and closes it again', async ({
    expectThemedScreenshot,
    mockApi,
    mount,
    page
  }) => {
    const component = await setUp({ mockApi, mount, page });
    await expect(userIcon(component)).toContainText('AA');
    const popper = await openUserMenu(component);

    for (const text of ['admin', 'Dark', 'Light', 'Logout']) {
      await expect(popper.getByText(text).first()).toBeVisible();
    }
    await expectThemedScreenshot(page, 'header-user-menu');

    await userIcon(component).click();
    await expect(page.getByText(labelEditProfile)).toHaveCount(0);
  });

  test('switches the theme', async ({ mockApi, mount, page }) => {
    const component = await setUp({ mockApi, mount, page });
    await openUserMenu(component);
    const themeSwitch = page.locator('[data-cy=themeSwitch]');
    const header = component.locator('header');

    await themeSwitch.click();
    await expect(header).toHaveCSS('background-color', 'rgb(0, 0, 0)');
    await themeSwitch.click();
    await expect(header).toHaveCSS('background-color', 'rgb(255, 255, 255)');
  });

  for (const [label, path] of [
    [labelLogout, '/logout'],
    [labelEditProfile, '/main.php?p=50104&o=c']
  ]) {
    test(`navigates from ${label}`, async ({ mockApi, mount, page }) => {
      const component = await setUp({ mockApi, mount, page });
      await openUserMenu(component);
      await page.getByText(label, { exact: true }).click();

      await expect(location(page)).toHaveValue(path);
    });
  }

  test('copies the autologin link', async ({ mockApi, mount, page }) => {
    const component = await setUp({ mockApi, mount, page });
    await openUserMenu(component);
    // The menu copies through execCommand on a hidden textarea, which a
    // headless browser does not forward to the clipboard: record the command
    // and what it copied. (The Cypress assertion sat in an unreturned promise
    // and never ran; the link is copied, not the bare key.)
    await page.evaluate(() => {
      const recorded: Array<string> = [];
      (window as unknown as { copied: Array<string> }).copied = recorded;
      document.execCommand = (command) => {
        const active = document.activeElement as HTMLTextAreaElement | null;
        recorded.push(
          `${command}:${active?.value?.slice(active.selectionStart, active.selectionEnd) ?? ''}`
        );

        return true;
      };
    });
    await page.getByText(labelCopyAutologinLink).click();

    const copied = await page.evaluate(
      () => (window as unknown as { copied: Array<string> }).copied
    );
    expect(copied).toHaveLength(1);
    expect(copied[0]).toMatch(
      /^copy:.*autologin=1&useralias=admin&token=LKEY-autologin$/
    );
  });

  test('enters fullscreen mode', async ({ mockApi, mount, page }) => {
    const component = await setUp({ mockApi, mount, page });
    await openUserMenu(component);
    await page.getByText(labelFullscreen).click();

    await expect(page.getByText(labelFullscreen)).toHaveCount(0);
  });

  for (const [fullname, username, expected] of [
    ['John', 'admin', 'JO'],
    ['John_Doe', 'admin', 'JD'],
    ['John Doe', 'admin', 'JD'],
    ['John_Doe_Due', 'admin', 'JD'],
    ['admin_plateforme', 'admin', 'AP'],
    ['', 'admin', 'AD'],
    ['', '', '?']
  ]) {
    test(`shows "${expected}" as the initials of "${fullname}" / "${username}"`, async ({
      mockApi,
      mount,
      page
    }) => {
      const component = await setUp(
        { mockApi, mount, page },
        { stubs: { user: { fullname, username } } }
      );

      await expect(userIcon(component)).toHaveText(expected);
    });
  }
});
