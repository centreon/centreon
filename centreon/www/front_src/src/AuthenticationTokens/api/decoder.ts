import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { equals } from 'ramda';
import { Creator, NamedEntity, Token } from '../Listing/models';
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

// Deleting a contact sets creator_id to NULL and keeps the token, while
// creator_name stays denormalized on the token row. A user id is never null:
// its column is NOT NULL and the token is deleted along with its user.
const getCreatorDecoder = (): JsonDecoder.Decoder<Creator> =>
  JsonDecoder.object<Creator>(
    {
      id: JsonDecoder.nullable(JsonDecoder.number),
      name: JsonDecoder.string
    },
    'creator'
  );

const tokenDecoder = JsonDecoder.object<Token>(
  {
    name: JsonDecoder.string,
    creationDate: JsonDecoder.string,
    creator: getCreatorDecoder(),
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
    creator: getCreatorDecoder(),
    expirationDate: JsonDecoder.nullable(JsonDecoder.string),
    isRevoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    token: JsonDecoder.string,
    user: JsonDecoder.optional(
      JsonDecoder.nullable(getNamedEntityDecoder('user'))
    ),
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
