import { JsonDecoder } from 'ts.data.json';
import { GetVaultConfiguration } from '../models';

export const getVaultConfigurationDecoder =
  JsonDecoder.object<GetVaultConfiguration>(
    {
      id: JsonDecoder.number,
      vaultId: JsonDecoder.number,
      address: JsonDecoder.string,
      port: JsonDecoder.number,
      rootPath: JsonDecoder.string,
      roleId: JsonDecoder.string
    },
    'Vault configuration',
    {
      rootPath: 'root_path',
      roleId: 'role_id',
      vaultId: 'vault_id'
    }
  );
