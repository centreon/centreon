import { JsonDecoder } from 'ts.data.json';

import {
  ThemeMode,
  ListingVariant,
  DashboardGlobalRole,
  DashboardRolesAndPermissions
} from '@centreon/ui-context';
import type { User } from '@centreon/ui-context';

import { PlatformInstallationStatus } from './models';

const dashboardDecoder = JsonDecoder.object<DashboardRolesAndPermissions>(
  {
    administrateRole: JsonDecoder.boolean,
    createRole: JsonDecoder.boolean,
    globalUserRole: JsonDecoder.enumeration<DashboardGlobalRole>(
      DashboardGlobalRole,
      'DashboardGlobalRole'
    ),
    viewRole: JsonDecoder.boolean
  },
  'Dashboard roles and permissions',
  {
    administrateRole: 'administrate_role',
    createRole: 'create_role',
    globalUserRole: 'global_user_role',
    viewRole: 'view_role'
  }
);

export const userDecoder = JsonDecoder.object<User>(
  {
    alias: JsonDecoder.string,
    dashboard: dashboardDecoder,
    default_page: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.string)
    ),
    isExportButtonEnabled: JsonDecoder.boolean,
    locale: JsonDecoder.string,
    name: JsonDecoder.string,
    themeMode: JsonDecoder.optional(
      JsonDecoder.enumeration<ThemeMode>(ThemeMode, 'ThemeMode')
    ),
    timezone: JsonDecoder.string,
    use_deprecated_pages: JsonDecoder.boolean,
    user_interface_density: JsonDecoder.enumeration(
      ListingVariant,
      ListingVariant.compact
    )
  },
  'User parameters',
  {
    isExportButtonEnabled: 'is_export_button_enabled',
    themeMode: 'theme'
  }
);

export const platformInstallationStatusDecoder =
  JsonDecoder.object<PlatformInstallationStatus>(
    {
      hasUpgradeAvailable: JsonDecoder.boolean,
      isInstalled: JsonDecoder.boolean
    },
    'Web versions',
    {
      hasUpgradeAvailable: 'has_upgrade_available',
      isInstalled: 'is_installed'
    }
  );
