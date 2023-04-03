export interface FederatedModule {
  federatedComponentsConfiguration: {
    federatedComponents: Array<string>;
    path: string;
    widgetMinHeight?: number;
    widgetMinWidth?: number;
  };
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
