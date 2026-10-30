import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import {
  iconDecoder,
  namedEntityDecoder
} from '../../Hosts/api/namedEntityDecoders';
import type { HostTemplateListItem } from '../models';

const hostTemplateDecoder = JsonDecoder.object<HostTemplateListItem>(
  {
    ...namedEntityDecoder,
    // Optional too, as on Hosts: a null may be dropped from the payload.
    alias: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string)),
    icon: JsonDecoder.optional(JsonDecoder.nullable(iconDecoder)),
    isLocked: JsonDecoder.boolean,
    linkedServiceTemplatesCount: JsonDecoder.number,
    parentTemplates: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Parent template'),
      'Parent templates'
    )
  },
  'Host template',
  {
    isLocked: 'is_locked',
    linkedServiceTemplatesCount: 'linked_service_templates_count',
    parentTemplates: 'parent_templates'
  }
);

export const hostTemplatesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: hostTemplateDecoder,
  entityDecoderName: 'Host template',
  listingDecoderName: 'Host templates List'
});
