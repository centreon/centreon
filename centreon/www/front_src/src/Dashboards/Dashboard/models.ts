import ReactGridLayout from 'react-grid-layout';

export interface PanelConfiguration {
  path: string;
  panelMinHeight?: number;
  panelMinWidth?: number;
}

export type Layout = Array<ReactGridLayout.Layout>;

export interface Panel extends ReactGridLayout.Layout {
  options?: object;
  panelConfiguration: PanelConfiguration;
}

export interface Dashboard {
  layout: Array<Panel>;
}
