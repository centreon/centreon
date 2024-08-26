import type { CommonWidgetProps } from 'src/models';

export interface PanelOptions {
  url: string;
}

export interface WebPageProps extends CommonWidgetProps<PanelOptions> {
  panelOptions: PanelOptions;
}
