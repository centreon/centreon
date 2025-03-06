import { SelectEntry } from '@centreon/ui';

export interface PanelOptions {
  backgroundColor?: string;
  countdown?: number;
  displayType: 'clock' | 'timer';
  locale?: SelectEntry;
  showDate: boolean;
  showTimezone: boolean;
  timeFormat?: '12' | '24';
  timezone?: SelectEntry;
}

export interface ForceDimension {
  forceHeight?: number;
  forceWidth?: number;
}
