export interface NamedEntity {
  id: number;
  name: string;
}

export interface Icon extends NamedEntity {
  url: string;
}

export interface ServiceHost extends NamedEntity {
  icon?: Icon | null;
}

export interface ServiceListItem extends NamedEntity {
  host: ServiceHost;
  icon?: Icon | null;
  isActivated: boolean;
  // Multiples of the engine interval length, already resolved through the
  // template chain; null when no template sets them either.
  normalCheckInterval?: number | null;
  retryCheckInterval?: number | null;
  // The direct template only, not the whole inheritance chain.
  template?: NamedEntity | null;
}

// The keys are the query parameters `useLoadData` sends them as.
export interface Filters {
  name: string;
  'host.name': string;
  template_id: NamedEntity | null;
  include_disabled_hosts: boolean;
  enabled: boolean;
  disabled: boolean;
}
