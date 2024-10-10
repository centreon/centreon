export interface PostVaultConfiguration {
  address: string;
  port: number;
  rootPath: string;
  roleId: string;
  secretId: string;
}

export interface PostVaultConfigurationAPI
  extends Pick<PostVaultConfiguration, 'address' | 'port'> {
  root_path: string;
  role_id: string;
  secret_id: string;
}

export type GetVaultConfiguration = Pick<
  PostVaultConfiguration,
  'address' | 'port' | 'rootPath'
>;
