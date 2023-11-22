export interface FederatedComponentsConfiguration {
  federatedComponents: Array<string>;
  panelMinHeight?: number;
  panelMinWidth?: number;
  path: string;
  title?: string;
}

export interface FederatedModule {
  federatedComponentsConfiguration: FederatedComponentsConfiguration;
  federatedPages: Array<PageComponent>;
  moduleFederationName: string;
  moduleName: string;
  remoteEntry: string;
  remoteUrl?: string;
}

interface PageComponent {
  component: string;
  route: string;
}

export interface StyleMenuSkeleton {
  className?: string;
  height?: number;
  width?: number;
}

export enum FederatedWidgetOptionType {
  metrics = 'metrics',
  metricsOnly = 'metrics-only',
  refreshInterval = 'refresh-interval',
  resources = 'resources',
  richText = 'rich-text',
  singleMetricGraphType = 'single-metric-graph-type',
  textfield = 'textfield',
  threshold = 'threshold',
  timePeriod = 'time-period',
  topBottomSettings = 'top-bottom-settings',
  valueFormat = 'value-format'
}

export interface FederatedWidgetOption {
  defaultValue: unknown;
  label: string;
  required?: boolean;
  type: FederatedWidgetOptionType;
}

export interface FederatedWidgetProperties {
  customBaseColor?: boolean;
  data: {
    [key: string]: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  };
  description: string;
  moduleName: string;
  options: {
    [key: string]: FederatedWidgetOption;
  };
  singleMetricSelection?: boolean;
  singleResourceTypeSelection?: boolean;
  title: string;
}
