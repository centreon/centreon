import { concat, pipe, prop, toLower } from 'ramda';

import { labelSoft } from '../../../translatedLabels';
import { selectableResourceTypes, selectableStatuses } from '../models';
import { build, getAutocompleteSuggestions, parse } from './index';

const search =
  'type:host,service state:unhandled status:ok,up status_type:soft host_group:Linux-Servers monitoring_server:Central host_category:Linux h.name:centreon parent_name:Centreon name:Service';

const builtSearch =
  'type:host,service state:unhandled status:ok,up status_type:soft host_group:Linux-Servers monitoring_server:Central host_category:Linux parent_name:Centreon name:Service "h.name:centreon"';

const parsedSearch = [
  {
    name: 'resource_types',
    object_type: null,
    type: 'multi_select',
    value: [
      { id: 'host', name: 'Host' },
      { id: 'service', name: 'Service' }
    ]
  },
  {
    name: 'states',
    object_type: null,
    type: 'multi_select',
    value: [{ id: 'unhandled_problems', name: 'Unhandled' }]
  },
  {
    name: 'statuses',
    object_type: null,
    type: 'multi_select',
    value: [
      { id: 'OK', name: 'Ok' },
      { id: 'UP', name: 'Up' }
    ]
  },
  {
    name: 'status_types',
    object_type: null,
    type: 'multi_select',
    value: [{ id: 'soft', name: labelSoft }]
  },
  {
    name: 'host_groups',
    object_type: 'host_groups',
    type: 'multi_select',
    value: [{ formattedName: 'Linux-Servers', id: 0, name: 'Linux-Servers' }]
  },
  {
    name: 'service_groups',
    object_type: 'service_groups',
    type: 'multi_select',
    value: []
  },
  {
    name: 'monitoring_servers',
    object_type: 'monitoring_servers',
    type: 'multi_select',
    value: [{ formattedName: 'Central', id: 0, name: 'Central' }]
  },
  {
    name: 'host_categories',
    object_type: 'host_categories',
    type: 'multi_select',
    value: [{ formattedName: 'Linux', id: 0, name: 'Linux' }]
  },
  {
    name: 'service_categories',
    object_type: 'service_categories',
    type: 'multi_select',
    value: []
  },
  {
    name: 'host_severities',
    object_type: 'host_severities',
    type: 'multi_select',
    value: []
  },
  {
    name: 'host_severity_levels',
    object_type: 'host_severity_levels',
    type: 'multi_select',
    value: []
  },
  {
    name: 'service_severities',
    object_type: 'service_severities',
    type: 'multi_select',
    value: []
  },
  {
    name: 'service_severity_levels',
    object_type: 'service_severity_levels',
    type: 'multi_select',
    value: []
  },
  {
    name: 'parent_names',
    object_type: 'parent_names',
    type: 'multi_select',
    value: [
      {
        formattedName: 'Centreon',
        id: 0,
        name: 'Centreon'
      }
    ]
  },
  {
    name: 'names',
    object_type: 'names',
    type: 'multi_select',
    value: [
      {
        formattedName: 'Service',
        id: 0,
        name: 'Service'
      }
    ]
  },
  {
    name: 'search',
    object_type: null,
    type: 'text',
    value: 'h.name:centreon'
  }
];

describe('parse', () => {
  it('parses the given search string into a Search model', () => {
    const result = parse({ search });

    expect(result).to.deep.equal(parsedSearch);
  });
});

describe('build', () => {
  it('builds a search string from the given Search model', () => {
    const result = build(parsedSearch);

    expect(result).to.be.equal(builtSearch);
  });
});

/**
 * Tests for colon-in-service-name support.
 * @see https://github.com/centreon/centreon/issues/9331
 */
describe('parse - services with colons in name (#9331)', () => {
  it('parses a quoted service name containing a colon as literal search text', () => {
    const result = parse({ search: '"DB: Backup"' });
    const searchCriteria = result.find(({ name }) => name === 'search');

    expect(searchCriteria).to.deep.include({
      name: 'search',
      object_type: null,
      type: 'text',
      value: 'DB: Backup'
    });
  });

  it('parses a quoted service name with colons alongside valid criteria', () => {
    const result = parse({
      search: 'type:service "HTTP: API Check" status:ok'
    });

    const searchCriteria = result.find(({ name }) => name === 'search');
    expect(searchCriteria).to.deep.include({
      name: 'search',
      type: 'text',
      value: 'HTTP: API Check'
    });

    const typeCriteria = result.find(({ name }) => name === 'resource_types');
    expect(typeCriteria).to.not.be.undefined;

    const statusCriteria = result.find(({ name }) => name === 'statuses');
    expect(statusCriteria).to.not.be.undefined;
  });

  it('treats an unquoted colon-containing token as raw search when prefix is not a valid criteria', () => {
    const result = parse({ search: 'DB:Backup' });
    const searchCriteria = result.find(({ name }) => name === 'search');

    expect(searchCriteria).to.deep.include({
      name: 'search',
      type: 'text',
      value: 'DB:Backup'
    });
  });

  it('handles multiple quoted segments', () => {
    const result = parse({
      search: '"Disk: Usage" "CPU: Load"'
    });
    const searchCriteria = result.find(({ name }) => name === 'search');

    expect(searchCriteria).to.deep.include({
      name: 'search',
      type: 'text',
      value: 'Disk: Usage CPU: Load'
    });
  });
});

describe('build - services with colons in name (#9331)', () => {
  it('wraps search text containing a colon in quotes', () => {
    const criteriasWithColonSearch = [
      ...parsedSearch.filter(({ name }) => name !== 'search'),
      {
        name: 'search',
        object_type: null,
        type: 'text',
        value: 'DB: Backup'
      }
    ];

    const result = build(criteriasWithColonSearch);

    expect(result).to.include('"DB: Backup"');
  });

  it('does not wrap search text without colons in quotes', () => {
    const criteriasWithNormalSearch = [
      ...parsedSearch.filter(({ name }) => name !== 'search'),
      {
        name: 'search',
        object_type: null,
        type: 'text',
        value: 'myservice'
      }
    ];

    const result = build(criteriasWithNormalSearch);

    expect(result).not.to.include('"myservice"');
    expect(result).to.include('myservice');
  });

  it('round-trips: build then parse preserves search text with colons', () => {
    const original = 'type:service "DB: Backup"';
    const parsed = parse({ search: original });
    const rebuilt = build(parsed);
    const reParsed = parse({ search: rebuilt });

    const originalSearch = parsed.find(({ name }) => name === 'search');
    const roundTripSearch = reParsed.find(({ name }) => name === 'search');

    expect(roundTripSearch?.value).to.equal(originalSearch?.value);
    expect(roundTripSearch?.value).to.equal('DB: Backup');
  });
});

describe('Autocomplete Suggestions', () => {
  const testCases = [
    {
      cursorPosition: 3,
      expectedResult: ['state:', 'status:', 'status_type:'],
      inputSearch: 'sta'
    },
    {
      cursorPosition: 6,
      expectedResult: [
        'unhandled',
        'acknowledged',
        'in_downtime',
        'in_flapping'
      ],
      inputSearch: 'state:'
    },
    {
      cursorPosition: 5,
      expectedResult: selectableResourceTypes.map(prop('id')),
      inputSearch: 'type:'
    },
    {
      cursorPosition: 15,
      expectedResult: [',acknowledged', ',in_downtime', ',in_flapping'],
      inputSearch: 'state:unhandled'
    },
    {
      cursorPosition: 16,
      expectedResult: ['acknowledged', 'in_downtime', 'in_flapping'],
      inputSearch: 'state:unhandled,'
    },
    {
      cursorPosition: 22,
      expectedResult: ['status:', 'status_type:'],
      inputSearch: 'state:unhandled statu'
    },
    {
      cursorPosition: 23,
      expectedResult: selectableStatuses.map(
        pipe(prop('id'), toLower),
        concat(',')
      ),
      inputSearch: 'state:unhandled status:'
    },
    {
      cursorPosition: 14,
      expectedResult: [],
      inputSearch: 'service_group:'
    },
    {
      cursorPosition: 11,
      expectedResult: [],
      inputSearch: 'host_group:'
    },
    {
      cursorPosition: 20,
      expectedResult: [],
      inputSearch: 'monitoring_server:'
    },
    {
      cursorPosition: 18,
      expectedResult: [],
      inputSearch: 'service_categorie:'
    },
    {
      cursorPosition: 15,
      expectedResult: [],
      inputSearch: 'host_categorie:'
    }
  ];

  testCases.forEach(({ cursorPosition, expectedResult, inputSearch }) => {
    it(`returns ${expectedResult} when ${inputSearch} is input at the ${cursorPosition} cursor position`, () => {
      expect(
        getAutocompleteSuggestions({
          cursorPosition,
          search: inputSearch
        })
      ).to.deep.equal(expectedResult);
    });
  });
});
