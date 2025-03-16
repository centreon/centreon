import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { NamedEntity, Token } from '../Listing/models';
import { CreatedToken } from '../Modal/models';

const getNamedEntityDecoder = (decoderName): JsonDecoder.Decoder<NamedEntity> =>
  JsonDecoder.object<NamedEntity>(
    {
      id: JsonDecoder.number,
      name: JsonDecoder.string
    },
    decoderName
  );

const tokenDecoder = JsonDecoder.object<Token>(
  {
    id: JsonDecoder.string,
    name: JsonDecoder.string,
    creationDate: JsonDecoder.string,
    creator: getNamedEntityDecoder('creator'),
    expirationDate: JsonDecoder.string,
    isRevoked: JsonDecoder.boolean,
    user: JsonDecoder.nullable(getNamedEntityDecoder('user')),
    type: JsonDecoder.optional(JsonDecoder.string) // for now
  },
  'ListedToken',
  {
    id: 'name',
    creationDate: 'creation_date',
    expirationDate: 'expiration_date',
    isRevoked: 'is_revoked'
  }
);

export const listTokensDecoder = buildListingDecoder<Token>({
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Tokens',
  listingDecoderName: 'listTokens'
});

export const createdTokenDecoder = JsonDecoder.object<CreatedToken>(
  {
    creationDate: JsonDecoder.string,
    creator: getNamedEntityDecoder('creator'),
    expirationDate: JsonDecoder.string,
    isRevoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    token: JsonDecoder.string,
    user: JsonDecoder.nullable(getNamedEntityDecoder('user')),
    type: JsonDecoder.optional(JsonDecoder.string) // for now
  },
  'CreatedToken',
  {
    creationDate: 'creation_date',
    expirationDate: 'expiration_date',
    isRevoked: 'is_revoked'
  }
);

const personalInformationDecoder = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'NamedEntity'
);

export const NamedEntityDecoder = buildListingDecoder<NamedEntity>({
  entityDecoder: personalInformationDecoder,
  entityDecoderName: 'NamedEntityn',
  listingDecoderName: 'listNamedEntity'
});
