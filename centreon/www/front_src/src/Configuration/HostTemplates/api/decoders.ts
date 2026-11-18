import { buildListingDecoder } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import {
  iconDecoder,
  namedEntityDecoder
} from '../../ResourceForm/api/decoders';
import { getDetailDecoder } from '../../ResourceForm/blocks';
import {
  blocks,
  type HostTemplateDetail,
  type PlatformContext
} from '../Form/sections';
import type { HostTemplateListItem } from '../models';

const hostTemplateDecoder = JsonDecoder.object<HostTemplateListItem>(
  {
    ...namedEntityDecoder,
    // Optional: `skip_null_values` drops a null alias or icon.
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

// The detail of one template, each block of the form reading its own fields.
export const getHostTemplateDecoder = (context: PlatformContext) =>
  getDetailDecoder<HostTemplateDetail>({
    blocks,
    context,
    name: 'Host template'
  });
