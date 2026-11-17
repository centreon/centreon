import { JsonDecoder } from 'ts.data.json';

import type { FieldBlock } from '../../../ResourceForm/models';

interface LockedDetail {
  isLocked: boolean;
}

// Read back only, to open a locked template read-only: the flag is never sent.
export const getLockedBlock = (): FieldBlock<LockedDetail> => ({
  defaultValues: { isLocked: false },
  detailDecoders: {
    isLocked: JsonDecoder.optional(JsonDecoder.boolean).map(
      (value) => value ?? false
    )
  },
  detailKeyMap: { isLocked: 'is_locked' },
  getInputs: () => ({}),
  getSchema: () => ({}),
  toPayload: () => ({})
});
