import { JsonDecoder } from 'ts.data.json';

import { Method } from '@centreon/ui';

import { Meta } from '../../../api/models';

export interface Payload {
  resources: Array<{ token_name: string; user_id: number }>;
}
export interface DataMutation {
  _meta?: Meta;
  payload?: Payload;
}

export interface DataApi {
  dataMutation?: DataMutation;
  decoder?: JsonDecoder.Decoder<object>;
  getEndpoint: (data: Meta) => string;
  method: Method;
  onSuccess?: (data) => void;
}
