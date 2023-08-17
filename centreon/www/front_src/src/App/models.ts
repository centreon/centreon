import { KeyValuePair } from 'ramda';

import { ListingVariant } from '@centreon/ui-context';

export interface DefaultParameters {
  monitoring_default_acknowledgement_force_active_checks: boolean;
  monitoring_default_acknowledgement_notify: boolean;
  monitoring_default_acknowledgement_persistent: boolean;
  monitoring_default_acknowledgement_sticky: boolean;
  monitoring_default_acknowledgement_with_services: boolean;
  monitoring_default_downtime_duration: string;
  monitoring_default_downtime_fixed: boolean;
  monitoring_default_downtime_with_services: boolean;
  monitoring_default_refresh_interval: string;
  resource_status_view_mode: ListingVariant;
}

type Translation = KeyValuePair<string, string>;
export type Translations = KeyValuePair<string, Translation>;

export interface CeipData {
  account?: Record<string, unknown>;
  cacheGenerationDate?: number;
  ceip: boolean;
  excludeAllText?: boolean;
  visitor?: Record<string, unknown>;
}

export interface CustomLoginPlatform {
  custom_text: string | null;
  icon_source: string | null;
  image_source: string | null;
  platform_name: string | null;
  text_position: string | null;
}
