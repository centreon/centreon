// DEMO ONLY, never merged: it stands in for the API Platform host template
// operations while the backend builds them, so the React page can be tried on
// a dev platform. It answers `./api/configuration/host_templates…` and grants
// the host template write permission; every other request reaches the real
// backend.
//
// Reads start from the platform's real templates, through the Core API
// (`/api/latest/configuration/hosts/templates`), turned into the proposed
// shape; the selectors and inherited macros are the real host form ones.
// Writes stay in memory and are lost on reload. Drop this branch as the
// endpoints land.
import { centreonBaseURL } from '@centreon/ui';

import axios from 'axios';
import { mergeDeepRight } from 'ramda';

type Entity = Record<string, unknown> & { id: number; name: string };
type Detail = Record<string, unknown> & { id: number; name: string };
type CoreTemplate = Record<string, unknown> & {
  id: number;
  name: string;
  is_locked: boolean;
};

const latency = 200;
const apiBase = `${centreonBaseURL}/api`;

const realFetch = window.fetch.bind(window);

const getJson = async (path: string): Promise<unknown> => {
  const response = await realFetch(`${apiBase}${path}`, {
    credentials: 'include'
  });

  if (!response.ok) {
    throw new Error(`${path} answered ${response.status}`);
  }

  return response.json();
};

// The real selectors the host form reads, by the kind the mock resolves ids
// with.
const selectorPaths: Record<string, string> = {
  categories: '/configuration/hosts/host_categories',
  commands: '/configuration/hosts/commands',
  contact_groups: '/configuration/hosts/contact_groups',
  contacts: '/configuration/hosts/contacts',
  host_templates: '/configuration/hosts/host_templates',
  medias: '/configuration/hosts/medias',
  severities: '/configuration/hosts/host_severities',
  timeperiods: '/configuration/hosts/timeperiods',
  timezones: '/configuration/timezones'
};

const selectorCache = new Map<string, Promise<Array<Entity>>>();

const loadSelector = (kind: string): Promise<Array<Entity>> => {
  if (!selectorCache.has(kind)) {
    selectorCache.set(
      kind,
      getJson(`${selectorPaths[kind]}?page=1&itemsPerPage=1000`)
        .then((body) => (body as { member: Array<Entity> }).member)
        .catch(() => [])
    );
  }

  return selectorCache.get(kind) as Promise<Array<Entity>>;
};

const lookUp = async (kind: string, id: unknown): Promise<Entity | null> => {
  if (id === null || id === undefined) {
    return null;
  }

  const entities = await loadSelector(kind);

  return (
    entities.find((entity) => entity.id === id) ?? {
      id: id as number,
      name: `#${id}`
    }
  );
};

// What the Core list and detail answer.
let coreTemplates: Promise<Array<CoreTemplate>> | null = null;

const loadCoreTemplates = (): Promise<Array<CoreTemplate>> => {
  if (!coreTemplates) {
    coreTemplates = getJson(
      '/latest/configuration/hosts/templates?page=1&limit=1000'
    )
      .then((body) => (body as { result: Array<CoreTemplate> }).result)
      .catch(() => []);
  }

  return coreTemplates;
};

const coreDetails = new Map<number, Promise<Detail | null>>();

// The real service templates, which nothing else serves to this form yet.
let serviceTemplates: Promise<Array<Entity>> | null = null;

const loadServiceTemplates = (): Promise<Array<Entity>> => {
  if (!serviceTemplates) {
    serviceTemplates = getJson(
      '/latest/configuration/services/templates?page=1&limit=1000'
    )
      .then((body) =>
        (body as { result: Array<Entity> }).result.map(({ id, name }) => ({
          id,
          name
        }))
      )
      .catch(() => []);
  }

  return serviceTemplates;
};

const toTriState = (value: unknown): string => {
  if (value === 1 || value === true) {
    return 'true';
  }

  if (value === 0 || value === false) {
    return 'false';
  }

  return 'use_default';
};

const notificationBits: Array<[number, string]> = [
  [1, 'down'],
  [2, 'unreachable'],
  [4, 'recovery'],
  [8, 'flapping'],
  [16, 'downtime_scheduled']
];

const toNotificationOptions = (value: unknown): Array<string> => {
  if (typeof value !== 'number') {
    return [];
  }

  if (value === 0) {
    return ['none'];
  }

  return notificationBits
    .filter(([bit]) => (value & bit) !== 0)
    .map(([, option]) => option);
};

// One Core template, in the shape the proposed detail endpoint answers.
const toDetailFromCore = async (core: CoreTemplate): Promise<Detail> => {
  const [macros, references] = await Promise.all([
    getJson(`/configuration/hosts/host_templates/${core.id}`)
      .then((body) => (body as { macros?: Array<unknown> }).macros ?? [])
      .catch(() => []),
    Promise.all([
      lookUp('commands', core.check_command_id),
      lookUp('timeperiods', core.check_timeperiod_id),
      lookUp('timeperiods', core.notification_timeperiod_id),
      lookUp('commands', core.event_handler_command_id),
      lookUp('medias', core.icon_id),
      lookUp('severities', core.severity_id),
      lookUp('timezones', core.timezone_id)
    ])
  ]);
  const [
    command,
    checkPeriod,
    notificationPeriod,
    eventHandler,
    icon,
    severity,
    timezone
  ] = references;

  return {
    alias: core.alias,
    categories: (core.categories as Array<Entity> | undefined) ?? [],
    check_options: {
      args: core.check_command_args ?? [],
      command,
      macros
    },
    data_processing: {
      acknowledgment_timeout: core.acknowledgement_timeout,
      check_freshness: toTriState(core.freshness_checked),
      event_handler: eventHandler,
      event_handler_args: core.event_handler_command_args ?? [],
      event_handler_enabled: toTriState(core.event_handler_enabled),
      flap_detection_enabled: toTriState(core.flap_detection_enabled),
      freshness_threshold: core.freshness_threshold,
      high_flap_threshold: core.high_flap_threshold,
      low_flap_threshold: core.low_flap_threshold
    },
    extended_informations: {
      action_url: core.action_url,
      alt_icon: core.icon_alternative,
      comment: core.comment,
      icon,
      note: core.note,
      note_url: core.note_url
    },
    id: core.id,
    is_locked: core.is_locked,
    name: core.name,
    // Contacts and contact groups: the Core API cannot read them.
    notifications: {
      contact_additive_inheritance: !!core.add_inherited_contact,
      contact_group_additive_inheritance: !!core.add_inherited_contact_group,
      contact_groups: [],
      contacts: [],
      enabled: toTriState(core.notification_enabled),
      first_delay: core.first_notification_delay,
      interval: core.notification_interval,
      options: toNotificationOptions(core.notification_options),
      recovery_delay: core.recovery_notification_delay,
      timeperiod: notificationPeriod
    },
    scheduling_options: {
      active_check_enabled: toTriState(core.active_check_enabled),
      check_period: checkPeriod,
      max_check_attempts: core.max_check_attempts,
      normal_check_interval: core.normal_check_interval,
      passive_check_enabled: toTriState(core.passive_check_enabled),
      retry_check_interval: core.retry_check_interval
    },
    // Not readable from the host template side today.
    service_templates: [],
    severity,
    snmp_version: core.snmp_version,
    templates: (core.templates as Array<Entity> | undefined) ?? [],
    timezone
  };
};

const loadCoreDetail = (id: number): Promise<Detail | null> => {
  if (!coreDetails.has(id)) {
    coreDetails.set(
      id,
      getJson(`/latest/configuration/hosts/templates/${id}`)
        .then((core) => toDetailFromCore(core as CoreTemplate))
        .catch(() => null)
    );
  }

  return coreDetails.get(id) as Promise<Detail | null>;
};

// What the demo changed: edited and created templates, and deleted ones.
const edited = new Map<number, Detail>();
const deleted = new Set<number>();
let nextId = 900000;

const getDetail = (id: number): Promise<Detail | null> => {
  if (deleted.has(id)) {
    return Promise.resolve(null);
  }

  return edited.has(id)
    ? Promise.resolve(edited.get(id) as Detail)
    : loadCoreDetail(id);
};

// A payload key holding one id, the detail key holding the entity, and the
// selector it comes from.
const singleReferences: Record<string, [string, string]> = {
  check_timeperiod_id: ['check_period', 'timeperiods'],
  command_id: ['command', 'commands'],
  event_handler_command_id: ['event_handler', 'commands'],
  icon_id: ['icon', 'medias'],
  severity_id: ['severity', 'severities'],
  timeperiod_id: ['timeperiod', 'timeperiods'],
  timezone_id: ['timezone', 'timezones']
};

const listReferences: Record<string, [string, string]> = {
  category_ids: ['categories', 'categories'],
  contact_groups: ['contact_groups', 'contact_groups'],
  contacts: ['contacts', 'contacts'],
  service_template_ids: ['service_templates', 'service_templates'],
  template_ids: ['templates', 'host_templates']
};

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const uniqueById = (entities: Array<Entity>): Array<Entity> => [
  ...new Map(entities.map((entity) => [entity.id, entity])).values()
];

const lookUpAny = async (kind: string, id: unknown): Promise<Entity | null> =>
  kind === 'service_templates'
    ? ((await loadServiceTemplates()).find((entity) => entity.id === id) ?? {
        id: id as number,
        name: `#${id}`
      })
    : lookUp(kind, id);

let nextMacroId = 1000000;

// A create, update or mass change body, turned into the detail the form reads
// back: ids become the named entities the decoders expect.
const toDetail = async (
  payload: Record<string, unknown>,
  current: Record<string, unknown> = {}
): Promise<Record<string, unknown>> => {
  const entries = await Promise.all(
    Object.entries(payload).map(
      async ([key, value]): Promise<[string, unknown]> => {
        const isAdd = key.endsWith('_to_add');
        const baseKey = key.replace(/_to_add$/, '');

        if (singleReferences[baseKey]) {
          const [target, kind] = singleReferences[baseKey];

          return [target, await lookUp(kind, value)];
        }

        if (listReferences[baseKey] && Array.isArray(value)) {
          const [target, kind] = listReferences[baseKey];
          const added = (await Promise.all(
            value.map((id) => lookUpAny(kind, id))
          )) as Array<Entity>;
          const existing = (current[target] as Array<Entity> | undefined) ?? [];

          return [target, uniqueById(isAdd ? [...existing, ...added] : added)];
        }

        if (key === 'macros' && Array.isArray(value)) {
          return [
            'macros',
            value.map((macro) => {
              nextMacroId += 1;

              return { ...macro, id: macro.id ?? nextMacroId };
            })
          ];
        }

        if (isObject(value)) {
          return [
            key,
            await toDetail(
              value,
              (current[key] as Record<string, unknown> | undefined) ?? {}
            )
          ];
        }

        // `notifications.options_to_add`.
        if (isAdd && Array.isArray(value)) {
          const existing = (current[baseKey] as Array<unknown>) ?? [];

          return [baseKey, [...new Set([...existing, ...value])]];
        }

        return [key, value];
      }
    )
  );

  return Object.fromEntries(entries);
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

const toListItem = (detail: Detail) => {
  const extended = (detail.extended_informations ?? {}) as Record<
    string,
    unknown
  >;

  return {
    alias: detail.alias ?? null,
    icon: extended.icon ?? null,
    id: detail.id,
    is_locked: !!detail.is_locked,
    name: detail.name,
    service_templates_count: (
      (detail.service_templates as Array<unknown> | undefined) ?? []
    ).length,
    templates: detail.templates ?? []
  };
};

const listTemplates = async (params: URLSearchParams) => {
  const core = await loadCoreTemplates();
  const isLocked = params.get('is_locked');
  const sortEntry = [...params.entries()].find(([key]) =>
    key.startsWith('sort[')
  );
  const sortField = sortEntry?.[0].slice(5, -1) ?? 'name';
  const sortOrder = sortEntry?.[1] === 'desc' ? -1 : 1;

  // The created ones, then the platform's, as the demo left them.
  const rows = [
    ...[...edited.values()].filter(
      ({ id }) => !core.some((template) => template.id === id)
    ),
    ...core
      .filter(({ id }) => !deleted.has(id))
      .map((template) => edited.get(template.id) ?? template)
  ]
    .filter((row) => matches(row.name, params.get('name[lk]')))
    .filter((row) => isLocked === null || String(!!row.is_locked) === isLocked)
    .sort(
      (a, b) =>
        sortOrder *
        String(a[sortField] ?? '').localeCompare(String(b[sortField] ?? ''))
    );

  // Only the page shown is read in detail, for its parents and icon.
  const page = await Promise.all(
    paginate(rows, params).map(
      async (row) =>
        (await getDetail(row.id as number)) ?? (row as unknown as Detail)
    )
  );

  return collection('host_templates', page.map(toListItem), rows.length);
};

const listServiceTemplates = async (params: URLSearchParams) => {
  const filtered = (await loadServiceTemplates()).filter((entity) =>
    matches(entity.name, params.get('name[lk]'))
  );

  return collection(
    'host_templates/service_templates',
    paginate(filtered, params),
    filtered.length
  );
};

const duplicateName = (name: string): string => `${name}_1`;

interface MockResponse {
  body?: unknown;
  status: number;
}

const notFound: MockResponse = {
  body: { code: 404, message: 'Host template not found (demo mock)' },
  status: 404
};

const handle = async (
  method: string,
  path: string,
  params: URLSearchParams,
  body: Record<string, unknown>
): Promise<MockResponse | null> => {
  const segments = path
    .replace(/^\/configuration\/host_templates\/?/, '')
    .split('/')
    .filter(Boolean);

  if (segments.length === 0) {
    if (method === 'GET') {
      return { body: await listTemplates(params), status: 200 };
    }

    if (method === 'POST') {
      nextId += 1;
      edited.set(nextId, {
        service_templates: [],
        ...(await toDetail(body)),
        id: nextId,
        is_locked: false
      } as Detail);

      return { body: { id: nextId }, status: 201 };
    }

    return null;
  }

  const [first, second] = segments;

  if (first === 'service_templates' && method === 'GET') {
    return { body: await listServiceTemplates(params), status: 200 };
  }

  const id = Number(first);

  if (Number.isNaN(id)) {
    return null;
  }

  const template = await getDetail(id);

  if (!template) {
    return notFound;
  }

  if (second === '_duplicate' && method === 'POST') {
    nextId += 1;
    edited.set(nextId, {
      ...structuredClone(template),
      id: nextId,
      is_locked: false,
      name: duplicateName(template.name)
    });

    return { status: 204 };
  }

  if (second) {
    return null;
  }

  // What the server refuses, as the Core API does.
  if (template.is_locked && ['PATCH', 'DELETE'].includes(method)) {
    return {
      body: { code: 400, message: 'The host template is locked (demo mock)' },
      status: 400
    };
  }

  switch (method) {
    case 'GET':
      return {
        body: {
          '@context': '/centreon/api/contexts/HostTemplate',
          '@id': `/centreon/api/configuration/host_templates/${id}`,
          '@type': 'HostTemplate',
          ...template
        },
        status: 200
      };
    case 'PATCH':
      edited.set(
        id,
        mergeDeepRight(template, await toDetail(body, template)) as Detail
      );

      return { status: 204 };
    case 'DELETE':
      edited.delete(id);
      deleted.add(id);

      return { status: 204 };
    default:
      return null;
  }
};

window.fetch = async (input, init): Promise<Response> => {
  const request = new Request(input, init);
  const url = new URL(request.url, window.location.origin);
  const apiPath = url.pathname.match(
    /\/api\/(configuration\/host_templates(\/.*)?)$/
  );

  if (!apiPath || url.pathname.includes('/api/latest/')) {
    return realFetch(input, init);
  }

  const text = await request.clone().text();
  const body = text ? JSON.parse(text) : {};
  const response = await handle(
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
    `[host templates demo mock] ${request.method} ${url.pathname}${url.search} → ${response.status}`
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
      data: { ...response.data, configuration_host_template_write: true }
    };
  }

  return response;
});
