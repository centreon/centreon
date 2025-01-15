import { CommonWidgetProps, Data, SeverityStatus } from '../../models';

export type Link = 'line' | 'curve' | 'step';

export interface PanelOptions {
  link: Link;
  pathStatuses: Array<SeverityStatus>;
  refreshInterval: 'default' | 'custom';
  refreshIntervalCustom?: number;
}

export interface WidgetProps extends CommonWidgetProps<PanelOptions> {
  panelData: Pick<Data, 'resources'>;
  panelOptions: PanelOptions;
}
