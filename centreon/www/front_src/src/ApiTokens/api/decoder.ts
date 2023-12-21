import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { PersonalInformation, Token } from '../TokenListing/models';

const personalInformationDecoder = (
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
    creator: personalInformationDecoder('creator'),
    expirationDate: JsonDecoder.string,
    isRevoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    user: personalInformationDecoder('user')
  },
  'Token',
  {
    creationDate: 'creation_date',
    expirationDate: 'expiration_date',
    isRevoked: 'is_revoked'
  }
);

export const listTokensDecoder = buildListingDecoder({
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Tokens',
  listingDecoderName: 'listTokens'
});
