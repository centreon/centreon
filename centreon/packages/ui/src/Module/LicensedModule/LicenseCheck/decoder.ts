import { JsonDecoder } from 'ts.data.json';

import { License } from './models';

export const licenseDecoder = JsonDecoder.object<License>(
  {
    success: JsonDecoder.boolean
  },
  'License'
);
