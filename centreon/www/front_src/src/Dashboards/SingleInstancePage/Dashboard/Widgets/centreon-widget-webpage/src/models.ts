import type { CommonWidgetProps } from '../../models';

export interface PanelOptions {
  url: string;
  refreshInterval: 'default' | 'custom' | 'manual';
  refreshIntervalCustom?: number;
}

export interface WebPageProps extends CommonWidgetProps<PanelOptions> {
  panelOptions: PanelOptions;
}
