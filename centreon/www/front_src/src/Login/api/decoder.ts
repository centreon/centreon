import { JsonDecoder } from 'ts.data.json';

import {
  LoginPageCustomisation,
  ProviderConfiguration,
  Redirect
} from '../models';

export const redirectDecoder = JsonDecoder.object<Redirect>(
  {
    passwordIsExpired: JsonDecoder.optional(JsonDecoder.boolean),
    redirectUri: JsonDecoder.string
  },
  'Redirect Decoder',
  {
    passwordIsExpired: 'password_is_expired',
    redirectUri: 'redirect_uri'
  }
);

const providerConfigurationDecoder = JsonDecoder.object<ProviderConfiguration>(
  {
    authenticationUri: JsonDecoder.string,
    id: JsonDecoder.number,
    isActive: JsonDecoder.boolean,
    isForced: JsonDecoder.optional(JsonDecoder.boolean),
    name: JsonDecoder.string
  },
  'Provider Condifugration',
  {
    authenticationUri: 'authentication_uri',
    isActive: 'is_active',
    isForced: 'is_forced'
  }
);

export const loginPageCustomisationDecoder =
  JsonDecoder.object<LoginPageCustomisation>(
    {
      customText: JsonDecoder.nullable(JsonDecoder.string),
      iconSource: JsonDecoder.nullable(JsonDecoder.string),
      imageSource: JsonDecoder.nullable(JsonDecoder.string),
      platformName: JsonDecoder.nullable(JsonDecoder.string),
      textPosition: JsonDecoder.nullable(JsonDecoder.string)
    },
    'Provider Condifugration',
    {
      customText: 'custom_text',
      iconSource: 'icon_source',
      imageSource: 'image_source',
      platformName: 'platform_name',
      textPosition: 'text_position'
    }
  );

export const providersConfigurationDecoder = JsonDecoder.array(
  providerConfigurationDecoder,
  'Providers Configuration List'
);
