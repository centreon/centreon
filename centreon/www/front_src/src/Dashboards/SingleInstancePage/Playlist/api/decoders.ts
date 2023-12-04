import { JsonDecoder } from 'ts.data.json';

export const namedEntityDecoder = JsonDecoder.object(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'entity'
);
