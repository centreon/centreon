import { JsonDecoder } from 'ts.data.json';

import {
  Icon,
  Notes,
  Parent,
  Resource,
  ResourceEndpoints,
  ResourceExternals,
  ResourceLinks,
  ResourceShortType,
  ResourceType,
  ResourceUris,
  Status,
  Severity,
  Acknowledgement,
  Downtime
} from './models';
import {
  Category,
  Group,
  ResourceDetails,
  Sensitivity
} from './Details/models';

const statusDecoder = JsonDecoder.object<Status>(
  {
    name: JsonDecoder.string,
    severity_code: JsonDecoder.number
  },
  'Status'
);

const severityIcon = JsonDecoder.object<Icon>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    url: JsonDecoder.string
  },
  'SeverityIcon'
);

const severityDecoder = JsonDecoder.object<Severity>(
  {
    icon: severityIcon,
    id: JsonDecoder.number,
    level: JsonDecoder.number,
    name: JsonDecoder.string,
    type: JsonDecoder.string
  },
  'Severity'
);

const resourceLinksEndpointDecoder = JsonDecoder.object<ResourceEndpoints>(
  {
    acknowledgement: JsonDecoder.optional(JsonDecoder.string),
    check: JsonDecoder.optional(JsonDecoder.string),
    details: JsonDecoder.optional(JsonDecoder.string),
    downtime: JsonDecoder.optional(JsonDecoder.string),
    forced_check: JsonDecoder.optional(JsonDecoder.string),
    metrics: JsonDecoder.optional(JsonDecoder.string),
    performance_graph: JsonDecoder.optional(JsonDecoder.string),
    sensitivity: JsonDecoder.optional(JsonDecoder.string),
    status_graph: JsonDecoder.optional(JsonDecoder.string),
    timeline: JsonDecoder.optional(JsonDecoder.string),
    timeline_download: JsonDecoder.optional(JsonDecoder.string)
  },
  'ResourceLinksEndpoints'
);

const resourceLinksExternalsEndpoints = JsonDecoder.object<ResourceExternals>(
  {
    action_url: JsonDecoder.optional(JsonDecoder.string),
    notes: JsonDecoder.optional(
      JsonDecoder.object<Notes>(
        {
          label: JsonDecoder.optional(JsonDecoder.string),
          url: JsonDecoder.string
        },
        'ResourceLinksExternalNotes'
      )
    )
  },
  'ResourceLinksExternals'
);

const resourceLinksUriDecoder = JsonDecoder.object<ResourceUris>(
  {
    configuration: JsonDecoder.optional(JsonDecoder.string),
    logs: JsonDecoder.optional(JsonDecoder.string),
    reporting: JsonDecoder.optional(JsonDecoder.string)
  },
  'ResourceLinksUris'
);

const resourceLinksDecoder = JsonDecoder.object<ResourceLinks>(
  {
    endpoints: resourceLinksEndpointDecoder,
    externals: JsonDecoder.optional(resourceLinksExternalsEndpoints),
    uris: resourceLinksUriDecoder
  },
  'ResourceLinks'
);

const resourceIconDecoder = JsonDecoder.object<Icon>(
  {
    id: JsonDecoder.optional(JsonDecoder.number),
    name: JsonDecoder.string,
    url: JsonDecoder.string
  },
  'ResourceIcon'
);

const commonDecoders = {
  duration: JsonDecoder.optional(JsonDecoder.string),
  has_active_checks_enabled: JsonDecoder.optional(JsonDecoder.boolean),
  has_passive_checks_enabled: JsonDecoder.optional(JsonDecoder.boolean),
  icon: JsonDecoder.optional(resourceIconDecoder),
  id: JsonDecoder.number,
  information: JsonDecoder.optional(JsonDecoder.string),
  is_acknowledged: JsonDecoder.optional(JsonDecoder.boolean),
  is_in_downtime: JsonDecoder.optional(JsonDecoder.boolean),
  is_notification_enabled: JsonDecoder.optional(JsonDecoder.boolean),
  last_check: JsonDecoder.optional(JsonDecoder.string),
  links: JsonDecoder.optional(resourceLinksDecoder),
  name: JsonDecoder.string,
  service_id: JsonDecoder.optional(JsonDecoder.number),
  severity: JsonDecoder.optional(severityDecoder),
  severity_level: JsonDecoder.optional(JsonDecoder.number),
  short_type: JsonDecoder.optional(
    JsonDecoder.oneOf<ResourceShortType>(
      [
        JsonDecoder.isExactly('h'),
        JsonDecoder.isExactly('m'),
        JsonDecoder.isExactly('s'),
        JsonDecoder.isExactly('a')
      ],
      'ResourceShortType'
    )
  ),
  status: JsonDecoder.optional(statusDecoder),
  tries: JsonDecoder.optional(JsonDecoder.string),
  type: JsonDecoder.enumeration<ResourceType>(ResourceType, 'ResourceType'),
  uuid: JsonDecoder.string
};

const resourceDecoder = JsonDecoder.object<Resource>(
  {
    ...commonDecoders,
    parent: JsonDecoder.optional(
      JsonDecoder.object<Parent>(commonDecoders, 'ResourceParent')
    ),
    uuid: JsonDecoder.string
  },
  'Resource'
);

const acknowledgementDecoder = JsonDecoder.object<Acknowledgement>(
  {
    author_name: JsonDecoder.string,
    comment: JsonDecoder.string,
    entry_time: JsonDecoder.string,
    is_persistent: JsonDecoder.boolean,
    is_sticky: JsonDecoder.boolean
  },
  'Acknowledgement'
);

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string,
  uuid: JsonDecoder.optional(JsonDecoder.string)
};

const categoryDecoder = JsonDecoder.object<Category>(
  {
    configuration_uri: JsonDecoder.nullable(JsonDecoder.string),
    ...namedEntityDecoder
  },
  'category'
);

const downtimeDecoder = JsonDecoder.object<Downtime>(
  {
    author_name: JsonDecoder.string,
    comment: JsonDecoder.string,
    end_time: JsonDecoder.string,
    entry_time: JsonDecoder.string,
    start_time: JsonDecoder.string
  },
  'downtime'
);

const groupDecoder = JsonDecoder.object<Group>(
  {
    configuration_uri: JsonDecoder.nullable(JsonDecoder.string),
    ...namedEntityDecoder
  },
  'group'
);

const sensitivityDecoder = JsonDecoder.object<Sensitivity>(
  {
    current_value: JsonDecoder.number,
    default_value: JsonDecoder.number,
    maximum_value: JsonDecoder.number,
    minimum_value: JsonDecoder.number
  },
  'sensitivity'
);

const resourceTypeDecoder = JsonDecoder.enumeration<ResourceType>(
  ResourceType,
  'resourceType'
);

const resourceDetailsDecoder = JsonDecoder.object<ResourceDetails>(
  {
    acknowledgement: JsonDecoder.optional(acknowledgementDecoder),
    alias: JsonDecoder.optional(JsonDecoder.string),
    calculation_type: JsonDecoder.optional(JsonDecoder.string),
    categories: JsonDecoder.optional(
      JsonDecoder.array<Category>(categoryDecoder, 'categoryArray')
    ),
    command_line: JsonDecoder.optional(JsonDecoder.string),
    downtimes: JsonDecoder.array<Downtime>(downtimeDecoder, 'downtimeArray'),
    duration: JsonDecoder.string,
    execution_time: JsonDecoder.number,
    flapping: JsonDecoder.boolean,
    fqdn: JsonDecoder.optional(JsonDecoder.string),
    groups: JsonDecoder.optional(
      JsonDecoder.array<Group>(groupDecoder, 'groupArray')
    ),
    has_active_checks_enabled: JsonDecoder.boolean,
    has_passive_checks_enabled: JsonDecoder.optional(JsonDecoder.boolean),
    id: JsonDecoder.number,
    information: JsonDecoder.string,
    is_acknowledged: JsonDecoder.boolean,
    is_in_downtime: JsonDecoder.boolean,
    last_check: JsonDecoder.string,
    last_notification: JsonDecoder.nullable(JsonDecoder.string),
    last_status_change: JsonDecoder.string,
    last_time_with_no_issue: JsonDecoder.nullable(JsonDecoder.string),
    latency: JsonDecoder.number,
    links: resourceLinksDecoder,
    monitoring_server_name: JsonDecoder.optional(JsonDecoder.string),
    name: JsonDecoder.string,
    next_check: JsonDecoder.string,
    notification_number: JsonDecoder.number,
    parent: JsonDecoder.optional(
      JsonDecoder.object<Parent>(commonDecoders, 'ResourceDetailsParent')
    ),
    percent_state_change: JsonDecoder.number,
    performance_data: JsonDecoder.optional(JsonDecoder.string),
    sensitivity: JsonDecoder.optional(sensitivityDecoder),
    severity: JsonDecoder.nullable(severityDecoder),
    severity_level: JsonDecoder.optional(JsonDecoder.number),
    status: statusDecoder,
    timezone: JsonDecoder.optional(JsonDecoder.string),
    tries: JsonDecoder.string,
    type: resourceTypeDecoder,
    uuid: JsonDecoder.string
  },
  'ResourceDetails',
  {
    has_active_checks_enabled: 'active_checks',
    has_passive_checks_enabled: 'passive_checks',
    is_acknowledged: 'acknowledged',
    is_in_downtime: 'in_downtime'
  }
);

export { statusDecoder, resourceDecoder, resourceDetailsDecoder };
