import { expect, test } from './fixtures';

// Migrated from Resources/Filter/Criterias/searchQueryLanguage/FilterSearchQuery:
// pure functions exposed on window by their story, called with page.evaluate().

const storyId = 'searchQueryLanguage/searchQueryLanguage/Default';

const search =
  'type:host,service state:unhandled status:ok,up status_type:soft host_group:Linux-Servers monitoring_server:Central host_category:Linux h.name:centreon parent_name:Centreon name:Service';

const builtSearch =
  'type:host,service state:unhandled status:ok,up status_type:soft host_group:Linux-Servers monitoring_server:Central host_category:Linux parent_name:Centreon name:Service h.name:centreon';

const multiSelect = (
  name: string,
  value: Array<unknown> = [],
  objectType: string | null = name
) => ({ name, object_type: objectType, type: 'multi_select', value });
const named = (name: string) => [{ formattedName: name, id: 0, name }];

const parsedSearch = (labelSoft: string) => [
  multiSelect(
    'resource_types',
    [
      { id: 'host', name: 'Host' },
      { id: 'service', name: 'Service' }
    ],
    null
  ),
  multiSelect(
    'states',
    [{ id: 'unhandled_problems', name: 'Unhandled' }],
    null
  ),
  multiSelect(
    'statuses',
    [
      { id: 'OK', name: 'Ok' },
      { id: 'UP', name: 'Up' }
    ],
    null
  ),
  multiSelect('status_types', [{ id: 'soft', name: labelSoft }], null),
  multiSelect('host_groups', named('Linux-Servers')),
  multiSelect('service_groups'),
  multiSelect('monitoring_servers', named('Central')),
  multiSelect('host_categories', named('Linux')),
  multiSelect('service_categories'),
  multiSelect('host_severities'),
  multiSelect('host_severity_levels'),
  multiSelect('service_severities'),
  multiSelect('service_severity_levels'),
  multiSelect('parent_names', named('Centreon')),
  multiSelect('names', named('Service')),
  { name: 'search', object_type: null, type: 'text', value: 'h.name:centreon' }
];

test.beforeEach(async ({ mount, page }) => {
  await mount(storyId);
  await page.waitForFunction(() => Boolean(window.searchQueryLanguage));
});

test('parses a search string into criterias', async ({ page }) => {
  const { labelSoft, parsed } = await page.evaluate((value) => {
    const api = window.searchQueryLanguage;

    return {
      labelSoft: api?.labelSoft ?? '',
      parsed: api?.parse({ search: value })
    };
  }, search);

  expect(parsed).toEqual(parsedSearch(labelSoft));
});

test('builds a search string from criterias', async ({ page }) => {
  const labelSoft = await page.evaluate(
    () => window.searchQueryLanguage?.labelSoft ?? ''
  );

  const built = await page.evaluate(
    (criterias) => window.searchQueryLanguage?.build(criterias as never),
    parsedSearch(labelSoft)
  );

  expect(built).toBe(builtSearch);
});

test('suggests the next criteria and values', async ({ page }) => {
  const { resourceTypeIds, statusIds } = await page.evaluate(() => ({
    resourceTypeIds: window.searchQueryLanguage?.resourceTypeIds ?? [],
    statusIds: window.searchQueryLanguage?.statusIds ?? []
  }));
  const cases = [
    {
      cursor: 3,
      expected: ['state:', 'status:', 'status_type:'],
      input: 'sta'
    },
    {
      cursor: 6,
      expected: ['unhandled', 'acknowledged', 'in_downtime', 'in_flapping'],
      input: 'state:'
    },
    { cursor: 5, expected: resourceTypeIds, input: 'type:' },
    {
      cursor: 15,
      expected: [',acknowledged', ',in_downtime', ',in_flapping'],
      input: 'state:unhandled'
    },
    {
      cursor: 16,
      expected: ['acknowledged', 'in_downtime', 'in_flapping'],
      input: 'state:unhandled,'
    },
    {
      cursor: 22,
      expected: ['status:', 'status_type:'],
      input: 'state:unhandled statu'
    },
    {
      cursor: 23,
      expected: statusIds.map((id) => id.toLowerCase()),
      input: 'state:unhandled status:'
    },
    { cursor: 14, expected: [], input: 'service_group:' },
    { cursor: 11, expected: [], input: 'host_group:' },
    { cursor: 20, expected: [], input: 'monitoring_server:' },
    { cursor: 18, expected: [], input: 'service_categorie:' },
    { cursor: 15, expected: [], input: 'host_categorie:' }
  ];

  for (const { cursor, expected, input } of cases) {
    const suggestions = await page.evaluate(
      ([cursorPosition, value]) =>
        window.searchQueryLanguage?.getAutocompleteSuggestions({
          cursorPosition: cursorPosition as number,
          search: value as string
        }),
      [cursor, input] as const
    );

    expect(suggestions, `"${input}" at ${cursor}`).toEqual(expected);
  }
});
