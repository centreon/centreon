import { JsonDecoder } from 'ts.data.json';

import type { ActionResult } from '../models';

export const actionResultDecoder = JsonDecoder.object<ActionResult>(
  {
    result: JsonDecoder.nullable(
      JsonDecoder.object<{ message: string | null }>(
        { message: JsonDecoder.nullable(JsonDecoder.string) },
        'ActionResultDetail'
      )
    ),
    status: JsonDecoder.boolean
  },
  'ActionResult'
);
