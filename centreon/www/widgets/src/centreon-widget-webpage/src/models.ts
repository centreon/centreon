import type { CommonWidgetProps } from 'src/models';

export interface PanelOptions {
  url: string;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
}

export interface WebPageProps extends CommonWidgetProps<PanelOptions> {
  panelOptions: PanelOptions;
}
