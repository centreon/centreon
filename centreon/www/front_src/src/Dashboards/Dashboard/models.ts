// TODO merge models on api level

import ReactGridLayout from 'react-grid-layout';

import { NamedEntity } from '../api/models';

export interface PanelConfiguration {
  isAddWidgetPanel?: boolean;
  panelMinHeight?: number;
  panelMinWidth?: number;
  path: string;
}

export type Layout = Array<ReactGridLayout.Layout>;

export interface Panel extends ReactGridLayout.Layout {
  name: string;
  options?: object;
  panelConfiguration: PanelConfiguration;
}

export interface Dashboard {
  layout: Array<Panel>;
}

export interface PanelDetailsToAPI extends NamedEntity {
  layout: {
    height: number;
    min_height: number;
    min_width: number;
    width: number;
    x: number;
    y: number;
  };
  widget_settings: {
    [key: string]: unknown;
  };
  widget_type: string;
}

export interface QuitWithoutSavedDashboard extends Dashboard {
  date: string;
  id?: number;
  name?: string;
}
