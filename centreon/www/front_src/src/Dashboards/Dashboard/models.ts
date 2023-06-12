import ReactGridLayout from 'react-grid-layout';

export interface PanelConfiguration {
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

export interface NamedEntity {
  id: number;
  name: string;
}

export interface PanelDetails extends NamedEntity {
  layout: {
    height: number;
    minHeight: number;
    minWidth: number;
    width: number;
    x: number;
    y: number;
  };
  widgetSettings: {
    [key: string]: unknown;
  };
  widgetType: string;
}

export interface DashboardDetails extends NamedEntity {
  createdAt: string;
  createdBy: NamedEntity;
  description: string | null;
  panels: Array<PanelDetails>;
  updatedAt: string;
  updatedBy: NamedEntity;
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
