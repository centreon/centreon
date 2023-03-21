export interface FederatedModule {
  federatedComponentsConfiguration: {
    federatedComponents: Array<string>;
    path: string;
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
