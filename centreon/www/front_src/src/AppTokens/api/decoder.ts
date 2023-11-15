import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import { PersonalInformation, Token } from '../tokenListing/models';

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
    creation_date: JsonDecoder.string,
    creator: personalInformationDecoder('creator'),
    expiration_date: JsonDecoder.string,
    is_revoked: JsonDecoder.boolean,
    name: JsonDecoder.string,
    user: personalInformationDecoder('user')
  },
  'Token'
);

export const listTokensDecoder = buildListingDecoder({
  entityDecoder: tokenDecoder,
  entityDecoderName: 'Tokens',
  listingDecoderName: 'listTokens'
});
