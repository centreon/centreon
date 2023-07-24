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
  textfield = 'textfield'
}

export interface FederatedWidgetOption {
  defaultValue: unknown;
  label: string;
  required?: boolean;
  type: FederatedWidgetOptionType;
}

export interface FederatedWidgetProperties {
  description: string;
  moduleName: string;
  options: {
    [key: string]: FederatedWidgetOption;
  };
  title: string;
}
