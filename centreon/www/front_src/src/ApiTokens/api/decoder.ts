import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { CreatedToken } from '../TokenCreation/models';
import { PersonalInformation, Token } from '../TokenListing/models';

const getPersonalInformationDecoder = (
  decoderName = 'personalInformation'
): JsonDecoder.Decoder<PersonalInformation> =>
  JsonDecoder.object<PersonalInformation>(
    {
      id: JsonDecoder.number,
      name: JsonDecoder.string
    },
    decoderName
  );

const tokenDecoder = JsonDecoder.object<Token>(
  {
    creationDate: JsonDecoder.string,
    creator: getPersonalInformationDecoder('creator'),
    expirationDate: JsonDecoder.string,
    isRevoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    user: getPersonalInformationDecoder('user')
  },
  'ListedToken',
  {
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
    creator: getPersonalInformationDecoder('creator'),
    expirationDate: JsonDecoder.string,
    isRevoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    token: JsonDecoder.string,
    user: getPersonalInformationDecoder('user')
  },
  'CreatedToken',
  {
    creationDate: 'creation_date',
    expirationDate: 'expiration_date',
    isRevoked: 'is_revoked'
  }
);

const personalInformationDecoder = JsonDecoder.object<PersonalInformation>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'PersonalInformation'
);

export const PersonalInformationDecoder =
  buildListingDecoder<PersonalInformation>({
    entityDecoder: personalInformationDecoder,
    entityDecoderName: 'PersonalInformationn',
    listingDecoderName: 'listPersonalInformation'
  });
