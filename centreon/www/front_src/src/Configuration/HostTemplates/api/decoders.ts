import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import {
  iconDecoder,
  namedEntityDecoder
} from '../../Hosts/api/namedEntityDecoders';
import type { HostTemplateListItem } from '../models';

// `skip_null_values`, as on the hosts listing: an unset field is absent.
const hostTemplateDecoder = JsonDecoder.object<HostTemplateListItem>(
  {
    ...namedEntityDecoder,
    alias: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    icon: JsonDecoder.optional(JsonDecoder.nullable(iconDecoder)),
    isLocked: JsonDecoder.optional(JsonDecoder.boolean).map(
      (value) => value ?? false
    ),
    serviceTemplatesCount: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.number)
    ),
    templates: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object(namedEntityDecoder, 'Template'),
        'Templates'
      )
    ).map((value) => value ?? [])
  },
  'Host template',
  {
    isLocked: 'is_locked',
    serviceTemplatesCount: 'service_templates_count'
  }
);

export const hostTemplatesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: hostTemplateDecoder,
  entityDecoderName: 'Host template',
  listingDecoderName: 'Host templates List'
});
