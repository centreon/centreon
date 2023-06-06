import { gt } from 'ramda';

export enum PanelSize {
  Small = 550,
  Medium = 675,
  Large = 800
}

interface PanelSizeParams {
  currentSize: number;
}

export const togglePanelSize = ({ currentSize }: PanelSizeParams): number => {
  return gt(currentSize, PanelSize.Medium) ? PanelSize.Small : PanelSize.Large;
};
