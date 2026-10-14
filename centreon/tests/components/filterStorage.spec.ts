import { expect, test } from './fixtures';

// Migrated from Resources/Filter/FilterStorage. The saved filter is written by
// an init script, before the application reads the local storage.
const storyId = 'Filter/FilterStorage/Default';
const filterKey = 'centreon-resource-status-23.10-filter';

const emptyListing = {
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: 0 },
  result: []
};
const listingOf = (result: Array<unknown>) => ({
  meta: { limit: 10, page: 1, search: {}, sort_by: {}, total: result.length },
  result
});

const linuxServers = {
  formattedName: 'Linux-servers',
  id: 0,
  name: 'Linux-servers'
};
const webAccess = { formattedName: 'Web-access', id: 0, name: 'Web-access' };
const multiSelect = (
  name: string,
  value: Array<unknown> = [],
  objectType: string | null = name
) => ({
  name,
  object_type: objectType,
  type: 'multi_select',
  value
});
const sort = { name: 'sort', value: ['status_severity_code', 'desc'] };

const savedFilter = {
  criterias: [
    multiSelect(
      'resource_types',
      [{ formattedName: 'Host', id: 'host', name: 'Host' }],
      null
    ),
    multiSelect('states', [{ id: 'acknowledged', name: 'Acknowledged' }], null),
    multiSelect('statuses', [{ id: 'OK', name: 'Ok' }], null),
    multiSelect('host_groups', [linuxServers]),
    multiSelect('service_groups', [webAccess]),
    { name: 'search', value: 'Search me' },
    sort
  ],
  id: 0,
  name: 'My filter'
};

const named = (formattedName: string, name = formattedName) => [
  { formattedName, id: 0, name }
];
const expectedFilter = {
  criterias: [
    multiSelect('resource_types', [{ id: 'host', name: 'Host' }], null),
    multiSelect('states', [], null),
    multiSelect(
      'statuses',
      [
        { id: 'UP', name: 'Up' },
        { id: 'DOWN', name: 'Down' }
      ],
      null
    ),
    multiSelect('status_types', [], null),
    multiSelect('host_groups', named('HG')),
    multiSelect('service_groups'),
    multiSelect('monitoring_servers', named('Poller\\stest', 'Poller test')),
    multiSelect('host_categories'),
    multiSelect('service_categories'),
    multiSelect('host_severities'),
    multiSelect('host_severity_levels'),
    multiSelect('service_severities'),
    multiSelect('service_severity_levels'),
    multiSelect('parent_names', named('Server')),
    multiSelect('names', named('Service')),
    { name: 'search', object_type: null, type: 'text', value: '' },
    sort
  ],
  id: 0,
  name: 'My filter'
};

const host = {
  alias: 'SensorProbe-Datacenter-04',
  chart_url: null,
  duration: '5m 23s',
  fqdn: 'SensorProbe-Datacenter-04',
  has_active_checks_enabled: true,
  has_passive_checks_enabled: false,
  host_id: 143,
  icon: {
    name: 'climate_64',
    url: '/centreon/img/media/Hardware/climate_64.png'
  },
  id: 143,
  information: 'OK - SensorProbe-Datacenter-04: rta 0.873ms, lost 0%',
  is_acknowledged: false,
  is_in_downtime: false,
  last_check: '2m 26s',
  last_status_change: '2023-10-11T17:05:57+02:00',
  links: {
    endpoints: {
      acknowledgement:
        '/centreon/api/latest/monitoring/hosts/143/acknowledgements?limit=1',
      details: '/centreon/api/latest/monitoring/resources/hosts/143',
      downtime: '/centreon/api/latest/monitoring/hosts/143/downtimes',
      notification_policy: null,
      performance_graph: null,
      status_graph: null,
      timeline: '/centreon/api/latest/monitoring/hosts/143/timeline'
    },
    externals: { action_url: '', notes: { label: '', url: '' } },
    uris: {
      configuration: '/centreon/main.php?p=60101&o=c&host_id=143',
      logs: '/centreon/main.php?p=20301&h=143',
      reporting: '/centreon/main.php?p=307&host=143'
    }
  },
  monitoring_server_name: 'Central',
  name: 'SensorProbe-Datacenter-04',
  notification_enabled: false,
  parent: null,
  performance_data: null,
  service_id: null,
  severity: null,
  short_type: 'h',
  status: { code: 0, name: 'UP', severity_code: 5 },
  tries: '1/5 (H)',
  type: 'host',
  uuid: 'h143'
};

test.use({ viewport: { height: 1000, width: 1200 } });

test.beforeEach(async ({ mockApi, page }) => {
  await page.addInitScript(
    ([key, value]) => localStorage.setItem(key as string, value as string),
    [filterKey, JSON.stringify(savedFilter)]
  );
  await mockApi({
    endpoint: './api/latest/users/filters/events-view',
    response: emptyListing
  });
  await mockApi({
    endpoint: './api/latest/monitoring/hostgroups',
    response: listingOf([linuxServers])
  });
  await mockApi({
    endpoint: './api/latest/monitoring/servicegroups',
    response: listingOf([webAccess])
  });
  await mockApi({
    endpoint: './api/latest/monitoring/servers',
    response: emptyListing
  });
  // Host listings answer the host; any other listing is empty.
  await mockApi({
    endpoint: './api/latest/monitoring/resources',
    response: (url: URL) =>
      url.searchParams.get('types') === '["host"]'
        ? listingOf([host])
        : emptyListing
  });
});

test('saves the typed filter in the local storage', async ({ mount, page }) => {
  const component = await mount(storyId);
  const search = component.getByPlaceholder('Search');

  await search.fill(
    'type:host parent_name:Server name:Service host_group:HG monitoring_server:Poller\\stest status:up,down'
  );

  await expect
    .poll(() => page.evaluate((key) => localStorage.getItem(key), filterKey))
    .toBe(JSON.stringify(expectedFilter));
});

test('restores the filter saved in the local storage', async ({
  expectThemedScreenshot,
  mount,
  page
}) => {
  const component = await mount(storyId);

  await expect(component.getByLabel('Unhandled alerts')).toHaveCount(0);
  await expect(component.getByPlaceholder('Search')).toHaveValue(
    'type:host state:acknowledged status:ok host_group:Linux-servers service_group:Web-access Search me'
  );

  await component.getByLabel('Filter options').click();
  await page.getByTestId('host').click();
  await expect(page.getByText(host.name)).toBeAttached();
  await expect(page.getByText('Acknowledged').first()).toBeAttached();
  await expect(page.getByText('Ok', { exact: true }).first()).toBeAttached();

  await page.getByLabel('Host group').click();
  await expect(page.getByText(linuxServers.name).first()).toBeVisible();
  await page.getByLabel('Service group').click();
  await expect(page.getByText(webAccess.name).first()).toBeVisible();

  await expectThemedScreenshot(page, 'filter-storage-options');
});
