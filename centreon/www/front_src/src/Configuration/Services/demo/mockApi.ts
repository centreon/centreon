// DEMO ONLY, never merged: it stands in for the Services API while the backend
// builds it, so the React page can be tried on a dev platform. It answers the
// `./api/configuration/services…` routes from an in-memory store and grants
// the service write permission; every other request reaches the real backend.
// Data resets on each page load. Drop this branch as the endpoints land.
import axios from 'axios';
import { mergeDeepRight } from 'ramda';

import {
  getCommandsResponse,
  getContactGroupsResponse,
  getContactsResponse,
  getGraphTemplatesResponse,
  getServiceCategoriesResponse,
  getServiceFormHostsResponse,
  getServiceFormServiceTemplatesResponse,
  getServiceFormTimePeriodsResponse,
  getServiceGroupsResponse,
  getServiceMediasResponse,
  getServiceResponse,
  getServiceSeveritiesResponse,
  getServiceTemplateMacrosResponses,
  getTimePeriodsResponse,
  getTrapsResponse,
  iconUrl
} from '../Specs/utils';

type Entity = Record<string, unknown> & { id: number; name: string };
type Detail = Record<string, unknown> & { id: number; name: string };

const latency = 200;

const members = (collection: { member: Array<unknown> }): Array<Entity> =>
  collection.member as Array<Entity>;

// The hosts a service can sit on; host C is disabled, to try the "Include
// disabled hosts" filter.
const hosts: Array<Entity & { activated: boolean }> = [
  {
    activated: true,
    icon: { id: 12, name: 'server.png', url: iconUrl },
    id: 10,
    name: 'host A'
  },
  { activated: true, id: 11, name: 'host B' },
  { activated: false, id: 12, name: 'host C (disabled)' },
  { activated: true, id: 13, name: 'Central' }
];

const uniqueById = (entities: Array<Entity>): Array<Entity> => [
  ...new Map(entities.map((entity) => [entity.id, entity])).values()
];

// Every selector's options, by the route segment the form reads them from.
const selectors: Record<string, Array<Entity>> = {
  commands: members(getCommandsResponse()),
  contact_groups: members(getContactGroupsResponse()),
  contacts: members(getContactsResponse()),
  graph_templates: members(getGraphTemplatesResponse()),
  hosts: uniqueById([...members(getServiceFormHostsResponse()), ...hosts]),
  medias: members(getServiceMediasResponse()),
  service_categories: members(getServiceCategoriesResponse()),
  service_groups: members(getServiceGroupsResponse()),
  service_severities: members(getServiceSeveritiesResponse()),
  service_templates: members(getServiceFormServiceTemplatesResponse()),
  timeperiods: uniqueById([
    ...members(getTimePeriodsResponse()),
    ...members(getServiceFormTimePeriodsResponse())
  ]),
  traps: members(getTrapsResponse())
};

// A payload key holding one id, the detail key holding the entity, and where
// to look the entity up.
const singleReferences: Record<string, [string, string]> = {
  check_timeperiod_id: ['check_period', 'timeperiods'],
  command_id: ['command', 'commands'],
  event_handler_command_id: ['event_handler', 'commands'],
  graph_template_id: ['graph_template', 'graph_templates'],
  host_id: ['host', 'hosts'],
  icon_id: ['icon', 'medias'],
  service_template_id: ['template', 'service_templates'],
  severity_id: ['severity', 'service_severities'],
  timeperiod_id: ['timeperiod', 'timeperiods']
};

// The same for the lists of ids. `contacts` and `contact_groups` keep their
// name: the payload sends ids where the detail holds entities.
const listReferences: Record<string, [string, string]> = {
  category_ids: ['categories', 'service_categories'],
  contact_groups: ['contact_groups', 'contact_groups'],
  contacts: ['contacts', 'contacts'],
  service_group_ids: ['service_groups', 'service_groups'],
  trap_ids: ['traps', 'traps']
};

const lookUp = (kind: string, id: unknown): Entity | null =>
  selectors[kind]?.find((entity) => entity.id === id) ??
  (typeof id === 'number' ? { id, name: `#${id}` } : null);

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

let nextMacroId = 1000;

// A create, update or mass change body, turned into the detail the form reads
// back: ids become the named entities the decoders expect.
const toDetail = (
  payload: Record<string, unknown>,
  current: Record<string, unknown> = {}
): Record<string, unknown> =>
  Object.entries(payload).reduce<Record<string, unknown>>(
    (detail, [key, value]) => {
      const mode = key.endsWith('_to_add')
        ? 'add'
        : key.endsWith('_to_remove')
          ? 'remove'
          : null;
      const baseKey = mode ? key.replace(/_to_(add|remove)$/, '') : key;

      if (singleReferences[baseKey]) {
        const [target, kind] = singleReferences[baseKey];

        return {
          ...detail,
          [target]: value === null ? null : lookUp(kind, value)
        };
      }

      if (listReferences[baseKey] && Array.isArray(value)) {
        const [target, kind] = listReferences[baseKey];
        const ids = value.map((item) => (isObject(item) ? item.id : item));
        const existing = (current[target] as Array<Entity> | undefined) ?? [];

        if (mode === 'remove') {
          return {
            ...detail,
            [target]: existing.filter((entity) => !ids.includes(entity.id))
          };
        }

        const added = ids.map((id) => lookUp(kind, id)) as Array<Entity>;

        return {
          ...detail,
          [target]:
            mode === 'add'
              ? uniqueById([...existing, ...added])
              : uniqueById(added)
        };
      }

      if (key === 'macros' && Array.isArray(value)) {
        return {
          ...detail,
          macros: value.map((macro) => {
            nextMacroId += 1;

            return { id: macro.id ?? nextMacroId, ...macro };
          })
        };
      }

      if (isObject(value)) {
        return {
          ...detail,
          [key]: toDetail(
            value,
            (current[key] as Record<string, unknown> | undefined) ?? {}
          )
        };
      }

      return { ...detail, [key]: value };
    },
    {}
  );

// The three services of the specs, then a few more so paging, sorting and
// the filters have something to work on.
const extraServices: Array<[string, number, number | null, boolean]> = [
  ['ping', 13, 30, true],
  ['http', 10, 31, true],
  ['https', 10, 31, true],
  ['load', 11, 30, true],
  ['swap', 11, null, false],
  ['uptime', 12, 30, true],
  ['ntp', 13, null, true],
  ['dns', 13, 31, true],
  ['ssh', 10, 30, true],
  ['mysql-connections', 11, 31, true],
  ['mysql-slow-queries', 11, 31, true],
  ['partitions', 13, 30, true]
];

let nextServiceId = 3;

const store = new Map<number, Detail>();

for (const id of [0, 1, 2]) {
  store.set(id, getServiceResponse(id) as Detail);
}

extraServices.forEach(([name, hostId, templateId, activated], index) => {
  const id = nextServiceId;

  nextServiceId += 1;
  // Service 1 carries every key the detail decoders require.
  store.set(id, {
    ...(getServiceResponse(1) as Detail),
    activated,
    host: lookUp('hosts', hostId),
    id,
    name,
    scheduling_options: {
      normal_check_interval: (index % 3) + 1,
      retry_check_interval: 1
    },
    template:
      templateId === null ? null : lookUp('service_templates', templateId)
  });
});

// What a row of the listing shows, read from the detail.
const toListItem = (service: Detail) => {
  const host = service.host as Entity | null;
  const scheduling = (service.scheduling_options ?? {}) as Record<
    string,
    unknown
  >;
  const extended = (service.extended_informations ?? {}) as Record<
    string,
    unknown
  >;
  const hostDefinition = hosts.find(({ id }) => id === host?.id);

  return {
    activated: service.activated ?? true,
    host: { ...host, icon: hostDefinition?.icon ?? null },
    icon: extended.icon ?? null,
    id: service.id,
    name: service.name,
    normal_check_interval: scheduling.normal_check_interval ?? null,
    retry_check_interval: scheduling.retry_check_interval ?? null,
    template: service.template ?? null
  };
};

const collection = (path: string, member: Array<unknown>, total?: number) => ({
  '@context': '/centreon/api/contexts/Collection',
  '@id': `/centreon/api/configuration/${path}`,
  '@type': 'Collection',
  member,
  totalItems: total ?? member.length
});

const matches = (value: unknown, search: string | null): boolean =>
  !search ||
  String(value ?? '')
    .toLowerCase()
    .includes(search.replace(/%/g, '').toLowerCase());

const paginate = <T>(items: Array<T>, params: URLSearchParams): Array<T> => {
  const page = Number(params.get('page') ?? 1);
  const limit = Number(params.get('itemsPerPage') ?? 10);

  return items.slice((page - 1) * limit, page * limit);
};

const listServices = (params: URLSearchParams) => {
  const templateId = params.get('template_id');
  const activated = params.get('activated');
  const includeDisabledHosts = params.get('include_disabled_hosts') === 'true';
  const sortEntry = [...params.entries()].find(([key]) =>
    key.startsWith('sort[')
  );
  const sortField = sortEntry?.[0].slice(5, -1) ?? 'host.name';
  const sortOrder = sortEntry?.[1] === 'desc' ? -1 : 1;

  const filtered = [...store.values()]
    .filter((service) => matches(service.name, params.get('name[lk]')))
    .filter((service) =>
      matches(
        (service.host as Entity | null)?.name,
        params.get('host.name[lk]')
      )
    )
    .filter(
      (service) =>
        !templateId ||
        String((service.template as Entity | null)?.id) === templateId
    )
    .filter(
      (service) =>
        activated === null || String(service.activated ?? true) === activated
    )
    .filter(
      (service) =>
        includeDisabledHosts ||
        hosts.find(({ id }) => id === (service.host as Entity | null)?.id)
          ?.activated !== false
    )
    .map(toListItem);

  const sortValue = (item: ReturnType<typeof toListItem>): string =>
    sortField === 'host.name'
      ? `${item.host?.name ?? ''} ${item.name}`
      : String(item.name);

  const sorted = filtered.sort(
    (a, b) => sortOrder * sortValue(a).localeCompare(sortValue(b))
  );

  return collection('services', paginate(sorted, params), sorted.length);
};

const listSelector = (kind: string, params: URLSearchParams) => {
  const filtered = (selectors[kind] ?? []).filter((entity) =>
    matches(entity.name, params.get('name[lk]'))
  );

  return collection(
    `services/${kind}`,
    paginate(filtered, params),
    filtered.length
  );
};

const duplicateName = (name: string): string => {
  const names = new Set([...store.values()].map((service) => service.name));
  let index = 1;

  while (names.has(`${name}_${index}`)) {
    index += 1;
  }

  return `${name}_${index}`;
};

interface MockResponse {
  body?: unknown;
  status: number;
}

const notFound: MockResponse = {
  body: { code: 404, message: 'Service not found (demo mock)' },
  status: 404
};

const handle = (
  method: string,
  path: string,
  params: URLSearchParams,
  body: Record<string, unknown>
): MockResponse | null => {
  // `/configuration/service_templates`, the listing's template filter.
  if (path === '/configuration/service_templates' && method === 'GET') {
    return { body: listSelector('service_templates', params), status: 200 };
  }

  const segments = path
    .replace(/^\/configuration\/services\/?/, '')
    .split('/')
    .filter(Boolean);

  if (segments.length === 0) {
    if (method === 'GET') {
      return { body: listServices(params), status: 200 };
    }

    if (method === 'POST') {
      const id = nextServiceId;

      nextServiceId += 1;
      store.set(id, {
        activated: true,
        ...toDetail(body),
        id
      } as unknown as Detail);

      return { body: { id }, status: 201 };
    }

    return null;
  }

  const [first, second] = segments;

  // One service template's macros, its own and those it inherits.
  if (first === 'service_templates' && second && method === 'GET') {
    const template = lookUp('service_templates', Number(second));

    return {
      body: getServiceTemplateMacrosResponses()[Number(second)] ?? {
        ...template,
        macros: []
      },
      status: 200
    };
  }

  if (selectors[first] && method === 'GET') {
    return { body: listSelector(first, params), status: 200 };
  }

  const id = Number(first);
  const service = store.get(id);

  if (Number.isNaN(id)) {
    return null;
  }

  if (!service) {
    return notFound;
  }

  if (second === '_duplicate' && method === 'POST') {
    const copyId = nextServiceId;

    nextServiceId += 1;
    store.set(copyId, {
      ...structuredClone(service),
      id: copyId,
      name: duplicateName(service.name)
    });

    return { status: 204 };
  }

  if (second) {
    return null;
  }

  switch (method) {
    case 'GET':
      return {
        body: {
          '@context': '/centreon/api/contexts/Service',
          '@id': `/centreon/api/configuration/services/${id}`,
          '@type': 'Service',
          ...service
        },
        status: 200
      };
    case 'PUT':
      store.set(id, {
        activated: service.activated,
        ...toDetail(body),
        id
      } as unknown as Detail);

      return { status: 204 };
    case 'PATCH':
      store.set(id, mergeDeepRight(service, toDetail(body, service)) as Detail);

      return { status: 204 };
    case 'DELETE':
      store.delete(id);

      return { status: 204 };
    default:
      return null;
  }
};

const realFetch = window.fetch.bind(window);

window.fetch = async (input, init): Promise<Response> => {
  const request = new Request(input, init);
  const url = new URL(request.url, window.location.origin);
  const apiPath = url.pathname.match(
    /\/api\/(configuration\/(services|service_templates)(\/.*)?)$/
  );

  // `./api/latest/configuration/services` is the Core API, which exists.
  if (!apiPath || url.pathname.includes('/api/latest/')) {
    return realFetch(input, init);
  }

  const text = await request.clone().text();
  const body = text ? JSON.parse(text) : {};
  const response = handle(
    request.method,
    `/${apiPath[1]}`,
    url.searchParams,
    body
  );

  if (!response) {
    return realFetch(input, init);
  }

  await new Promise((resolve) => setTimeout(resolve, latency));

  // biome-ignore lint/suspicious/noConsole: says the page reads made-up data.
  console.info(
    `[services demo mock] ${request.method} ${url.pathname}${url.search} → ${response.status}`
  );

  return new Response(
    response.body === undefined ? null : JSON.stringify(response.body),
    {
      headers: { 'Content-Type': 'application/json' },
      status: response.status
    }
  );
};

// The permission the backend does not expose yet: without it the page is
// read-only, even for an administrator.
axios.interceptors.response.use((response) => {
  if (response.config.url?.includes('/users/acl/permissions')) {
    return {
      ...response,
      data: { ...response.data, configuration_service_write: true }
    };
  }

  return response;
});
