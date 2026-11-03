import { JsonDecoder } from 'ts.data.json';

import { getRelationListBlock } from '../../../ResourceForm/fieldBlocks/relationList';
import type {
  FieldBlock,
  NamedEntity,
  ResourceOptions
} from '../../../ResourceForm/models';
import { labelServiceTraps } from '../../translatedLabels';

interface Trap {
  id: number;
  name: string;
  vendor?: NamedEntity | null;
}

// A trap reads `vendor - trap`, as in the legacy form: the selector sends the
// vendor apart, as `{ id, name }`.
export const trapDecoder = JsonDecoder.object<Trap>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    vendor: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<NamedEntity>(
          { id: JsonDecoder.number, name: JsonDecoder.string },
          'Vendor'
        )
      )
    )
  },
  'Trap'
).map<NamedEntity>(({ id, name, vendor }) => ({
  id,
  name: vendor ? `${vendor.name} - ${name}` : name
}));

// The SNMP traps that update the service. On-premise only: the cloud API has
// no trap and refuses them.
export const getSnmpTrapsBlock = ({
  endpoint,
  resource
}: {
  endpoint: string;
  resource: ResourceOptions;
}): FieldBlock<Record<'snmpTraps', Array<NamedEntity>>> =>
  getRelationListBlock({
    decoderNames: ['Trap', 'Traps'],
    detailKey: 'traps',
    endpoint,
    entityDecoder: trapDecoder,
    id: 'snmpTraps',
    isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
    label: labelServiceTraps,
    name: 'traps',
    payloadKey: 'trap_ids',
    resource
  });
