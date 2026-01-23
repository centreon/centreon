import { JsonDecoder } from 'ts.data.json';

import type { License } from './models';

export const licenseDecoder = JsonDecoder.object<License>(
  {
    success: JsonDecoder.boolean
  },
  'License'
);
