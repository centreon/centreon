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
  refreshInterval = 'refresh-interval',
  resources = 'resources',
  richText = 'rich-text',
  textfield = 'textfield',
  threshold = 'threshold'
}

export interface FederatedWidgetOption {
  defaultValue: unknown;
  label: string;
  required?: boolean;
  type: FederatedWidgetOptionType;
}

export interface FederatedWidgetProperties {
  data: {
    [key: string]: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  };
  description: string;
  moduleName: string;
  options: {
    [key: string]: FederatedWidgetOption;
  };
  singleMetricSelection?: boolean;
  title: string;
}
