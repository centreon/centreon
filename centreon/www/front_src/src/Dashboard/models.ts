import ReactGridLayout from 'react-grid-layout';

export interface WidgetConfiguration {
  path: string;
  widgetMinHeight?: number;
  widgetMinWidth?: number;
}

export enum Breakpoint {
  lg = 'lg',
  md = 'md',
  sm = 'sm'
}

export enum ColumnByBreakpoint {
  lg = 8,
  md = 4,
  sm = 2
}

export type ResponsiveLayouts = Record<
  Breakpoint,
  Array<ReactGridLayout.Layout>
>;

export interface Dashboard {
  layouts: ResponsiveLayouts;
  settings: Array<{
    i: string;
    options?: object;
    widgetConfiguration: WidgetConfiguration;
  }>;
}

export interface WidgetLayoutWithSetting extends ReactGridLayout.Layout {
  options?: object;
}
