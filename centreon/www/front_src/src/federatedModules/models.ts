import { SelectEntry } from '@centreon/ui';

export interface FederatedComponentsConfiguration {
  federatedComponents: Array<string>;
  panelMinHeight?: number;
  panelMinWidth?: number;
  path: string;
  title?: string;
}

export interface FederatedModule {
  federatedComponentsConfiguration: Array<FederatedComponentsConfiguration>;
  federatedPages: Array<PageComponent>;
  moduleFederationName: string;
  moduleName: string;
  remoteEntry: string;
  remoteUrl?: string;
}

interface PageComponent {
  children?: string;
  component: string;
  route: string;
}

export interface StyleMenuSkeleton {
  className?: string;
  height?: number;
  width?: number;
}

export enum FederatedWidgetOptionType {
  checkbox = 'checkbox',
  metrics = 'metrics',
  radio = 'radio',
  refreshInterval = 'refresh-interval',
  resources = 'resources',
  richText = 'rich-text',
  singleMetricGraphType = 'single-metric-graph-type',
  textfield = 'textfield',
  threshold = 'threshold',
  tiles = 'tiles',
  timePeriod = 'time-period',
  topBottomSettings = 'top-bottom-settings',
  valueFormat = 'value-format'
}

export interface FederatedWidgetOption {
  defaultValue:
    | unknown
    | {
        is: unknown;
        otherwise: unknown;
        then: unknown;
        when: string;
      };
  label: string;
  options?:
    | Array<SelectEntry>
    | {
        is: unknown;
        otherwise: Array<SelectEntry>;
        then: Array<SelectEntry>;
        when: string;
      };
  required?: boolean;
  secondaryLabel: string;
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
  singleHostPerMetric?: boolean;
  singleMetricSelection?: boolean;
  title: string;
}
