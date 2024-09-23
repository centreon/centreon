import type { SelectEntry } from '@centreon/ui';

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
  preloadScript?: string;
  remoteEntry: string;
  remoteUrl?: string;
}

export interface PageComponent {
  children?: string;
  component: string;
  featureFlag?: string;
  route: string;
}

export interface StyleMenuSkeleton {
  className?: string;
  height?: number;
  width?: number;
}

export enum FederatedWidgetOptionType {
  autocomplete = 'autocomplete',
  buttonGroup = 'button-group',
  checkbox = 'checkbox',
  color = 'color',
  connectedAutocomplete = 'connected-autocomplete',
  datePicker = 'date-picker',
  displayType = 'displayType',
  locale = 'locale',
  metrics = 'metrics',
  radio = 'radio',
  refreshInterval = 'refresh-interval',
  resources = 'resources',
  richText = 'rich-text',
  select = 'select',
  singleMetricGraphType = 'single-metric-graph-type',
  slider = 'slider',
  switch = 'switch',
  text = 'text',
  textfield = 'textfield',
  threshold = 'threshold',
  tiles = 'tiles',
  timeFormat = 'time-format',
  timePeriod = 'time-period',
  timezone = 'timezone',
  topBottomSettings = 'top-bottom-settings',
  valueFormat = 'value-format',
  warning = 'warning'
}

interface WidgetHiddenCondition {
  matches: unknown;
  method: 'equals' | 'includes';
  property?: string;
  target: 'options' | 'data' | 'modules' | 'featureFlags';
  when: string;
}

export interface SubInput {
  direction?: 'row' | 'column';
  displayValue: unknown;
  input: Omit<FederatedWidgetOption, 'group' | 'hiddenCondition' | 'subInputs'>;
  name: string;
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
  group?: string;
  hasModule?: string;
  hiddenCondition: WidgetHiddenCondition;
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
  subInputs?: Array<SubInput>;
  type: FederatedWidgetOptionType;
}

export interface FederatedWidgetProperties {
  categories?: {
    [category: string]: {
      elements: {
        [key: string]: FederatedWidgetOption & {
          group?: string;
        };
      };
      groups: Array<SelectEntry>;
      hasModule?: string;
      availableOnPremOnly?: boolean;
    };
  };
  customBaseColor?: boolean;
  data: {
    [key: string]: Pick<FederatedWidgetOption, 'defaultValue' | 'type'>;
  };
  description: string;
  icon?: string;
  moduleName: string;
  options: {
    [key: string]: FederatedWidgetOption;
  };
  singleMetricSelection?: boolean;
  singleResourceSelection?: boolean;
  title: string;
  message?: {
    label: string;
    icon?: string;
  };
}
