import { JsonDecoder } from 'ts.data.json';

import type { User } from '@centreon/ui-context';
import {
  DashboardGlobalRole,
  DashboardRolesAndPermissions,
  FeatureFlags,
  ListingVariant,
  PlatformFeatures,
  ThemeMode
} from '@centreon/ui-context';

import {
  PlatformInstallationStatus,
  PlatformVersions,
  Version
} from './models';

const dashboardDecoder = JsonDecoder.object<DashboardRolesAndPermissions>(
  {
    createDashboards: JsonDecoder.failover(false, JsonDecoder.boolean),
    globalUserRole: JsonDecoder.failover(
      DashboardGlobalRole.viewer,
      JsonDecoder.enumeration<DashboardGlobalRole>(
        DashboardGlobalRole,
        'DashboardGlobalRole'
      )
    ),
    manageAllDashboards: JsonDecoder.failover(false, JsonDecoder.boolean),
    viewDashboards: JsonDecoder.failover(false, JsonDecoder.boolean)
  },
  'Dashboard roles and permissions',
  {
    createDashboards: 'create_dashboards',
    globalUserRole: 'global_user_role',
    manageAllDashboards: 'administrate_dashboards',
    viewDashboards: 'view_dashboards'
  }
);

export const userDecoder = JsonDecoder.object<User>(
  {
    alias: JsonDecoder.string,
    canManageApiTokens: JsonDecoder.boolean,
    dashboard: JsonDecoder.failover(
      null,
      JsonDecoder.optional(JsonDecoder.nullable(dashboardDecoder))
    ),
    default_page: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.string)
    ),
    id: JsonDecoder.number,
    isAdmin: JsonDecoder.optional(JsonDecoder.boolean),
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
    canManageApiTokens: 'can_manage_api_tokens',
    isAdmin: 'is_admin',
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

const versionDecoder = JsonDecoder.object<Version>(
  {
    fix: JsonDecoder.string,
    major: JsonDecoder.string,
    minor: JsonDecoder.string,
    version: JsonDecoder.string
  },
  'Version'
);

export const platformVersionsDecoder = JsonDecoder.object<PlatformVersions>(
  {
    modules: JsonDecoder.dictionary(versionDecoder, 'Modules'),
    web: versionDecoder,
    widgets: JsonDecoder.dictionary(
      JsonDecoder.nullable(versionDecoder),
      'Widgets'
    )
  },
  'Platform versions',
  {
    modules: 'modules',
    web: 'web',
    widgets: 'widgets'
  }
);

export const featuresFlagDecoder = JsonDecoder.object<FeatureFlags>(
  {
    adExclusionPeriods: JsonDecoder.optional(JsonDecoder.boolean),
    notification: JsonDecoder.optional(JsonDecoder.boolean),
    vault: JsonDecoder.optional(JsonDecoder.boolean)
  },
  'Feature flags',
  {
    adExclusionPeriods: 'ad_exclusion_periods',
  }
);

export const platformFeaturesDecoder = JsonDecoder.object<PlatformFeatures>(
  {
    featureFlags: featuresFlagDecoder,
    isCloudPlatform: JsonDecoder.boolean
  },
  'Platform features',
  {
    featureFlags: 'feature_flags',
    isCloudPlatform: 'is_cloud_platform'
  }
);
