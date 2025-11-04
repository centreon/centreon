import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import {
  AdditionalConnectorListItem,
  NamedEntity,
  Parameter,
  ParameterKeys
} from '../models';

const namedEntityDecoder = {
  id: JsonDecoder.number,
  name: JsonDecoder.string
};

const additionalConnectorsDecoder =
  JsonDecoder.object<AdditionalConnectorListItem>(
    {
      ...namedEntityDecoder,
      description: JsonDecoder.nullable(JsonDecoder.string),
      type: JsonDecoder.string
    },
    'Additional connector'
  );

export const additionalConnectorsListDecoder = buildListingDecoder({
  entityDecoder: additionalConnectorsDecoder,
  entityDecoderName: 'Additional connector',
  listingDecoderName: 'Additional connectors List'
});

const vcenterDecoder = JsonDecoder.object<Parameter>(
  {
    [ParameterKeys.name]: JsonDecoder.string,
    [ParameterKeys.url]: JsonDecoder.string,
    [ParameterKeys.username]: JsonDecoder.nullable(JsonDecoder.string),
    [ParameterKeys.password]: JsonDecoder.nullable(JsonDecoder.string)
  },
  'vcenter',
  {
    [ParameterKeys.name]: 'name',
    [ParameterKeys.url]: 'url',
    [ParameterKeys.username]: 'username',
    [ParameterKeys.password]: 'password'
  }
);

const connectorsParametersDecoder = JsonDecoder.object<{
  port: number;
  vcenters: Array<Parameter>;
}>(
  {
    port: JsonDecoder.number,
    vcenters: JsonDecoder.array(vcenterDecoder, 'vcenters')
  },
  'connector parameters'
);

export const additionalConnectorDecoder = JsonDecoder.object(
  {
    ...namedEntityDecoder,
    description: JsonDecoder.nullable(JsonDecoder.string),
    parameters: connectorsParametersDecoder,
    pollers: JsonDecoder.array(
      JsonDecoder.object<NamedEntity>(namedEntityDecoder, 'Updated By'),
      'pollers'
    ),
    type: JsonDecoder.string
  },
  'Connector Configuration'
).map((connector) => ({
  ...connector,
  type: 1
}));
