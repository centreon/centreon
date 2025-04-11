import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { equals } from 'ramda';
import { NamedEntity, Token } from '../Listing/models';
import { CreatedToken } from '../Modal/models';
import { TokenType } from '../models';

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
    name: JsonDecoder.string,
    creationDate: JsonDecoder.string,
    creator: getNamedEntityDecoder('creator'),
    expirationDate: JsonDecoder.nullable(JsonDecoder.string),
    isRevoked: JsonDecoder.boolean,
    user: JsonDecoder.optional(getNamedEntityDecoder('user')),
    type: JsonDecoder.string
  },
  'ListedToken',
  {
    creationDate: 'creation_date',
    expirationDate: 'expiration_date',
    isRevoked: 'is_revoked'
  }
).map((token) => {
  return {
    ...token,
    id: equals(token.type, TokenType.CMA)
      ? `${token.name}_${token.creator.id}`
      : `${token.name}_${token?.user?.id}`
  };
});

export const listTokensDecoder = buildListingDecoder<Token>({
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Tokens',
  listingDecoderName: 'listTokens'
});

export const createdTokenDecoder = JsonDecoder.object<CreatedToken>(
  {
    creationDate: JsonDecoder.string,
    creator: getNamedEntityDecoder('creator'),
    expirationDate: JsonDecoder.nullable(JsonDecoder.string),
    isRevoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    token: JsonDecoder.string,
    user: JsonDecoder.nullable(getNamedEntityDecoder('user')),
    type: JsonDecoder.string
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
