import { JsonDecoder } from 'ts.data.json';
import { GetVaultConfiguration } from '../models';

export const getVaultConfigurationDecoder =
  JsonDecoder.object<GetVaultConfiguration>(
    {
      address: JsonDecoder.string,
      port: JsonDecoder.number,
      rootPath: JsonDecoder.string,
    },
    'Vault configuration',
    {
      rootPath: 'root_path',
    }
  );
