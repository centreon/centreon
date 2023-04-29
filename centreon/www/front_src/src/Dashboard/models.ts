import ReactGridLayout from 'react-grid-layout';

export interface WidgetConfiguration {
  path: string;
  widgetMinHeight?: number;
  widgetMinWidth?: number;
}

export type Layout = Array<ReactGridLayout.Layout>;

export interface Widget extends ReactGridLayout.Layout {
  options?: object;
  widgetConfiguration: WidgetConfiguration;
}

export interface Dashboard {
  layout: Array<Widget>;
}
