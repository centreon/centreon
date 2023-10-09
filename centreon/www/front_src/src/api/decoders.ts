import { JsonDecoder } from 'ts.data.json';

import {
  ThemeMode,
  ListingVariant,
  FeatureFlags,
  PlatformFeatures
} from '@centreon/ui-context';
import type { User } from '@centreon/ui-context';

import {
  PlatformInstallationStatus,
  PlatformVersions,
  Version
} from './models';

export const userDecoder = JsonDecoder.object<User>(
  {
    alias: JsonDecoder.string,
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
    widgets: JsonDecoder.dictionary(versionDecoder, 'Widgets')
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
    dashboard: JsonDecoder.optional(JsonDecoder.boolean),
    notification: JsonDecoder.optional(JsonDecoder.boolean),
    resourceStatusTreeView: JsonDecoder.optional(JsonDecoder.boolean),
    vault: JsonDecoder.optional(JsonDecoder.boolean)
  },
  'Feature flags',
  {
    adExclusionPeriods: 'ad_exclusion_periods',
    resourceStatusTreeView: 'resource_status_tree_view'
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
