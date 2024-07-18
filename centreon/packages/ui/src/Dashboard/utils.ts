import { lt } from 'ramda';
import { Layout } from 'react-grid-layout';

const minColumns = 1;
const breakpoint = 768;

export const rowHeight = 64;
export const maxColumns = 12;

export const getIsSmallScreenSize = (): boolean =>
  lt(window.innerWidth, breakpoint);

export const getColumnsFromScreenSize = (): number =>
  getIsSmallScreenSize() ? minColumns : maxColumns;

export const getLayout = <T extends Layout>(layout: Array<T>): Array<T> => {
  const isSmallScreenSize = getIsSmallScreenSize();
  if (!isSmallScreenSize) {
    return layout;
  }

  return layout
    .sort((a, b) => a.x + a.y - (b.x + b.y))
    .map((widget) => ({
      ...widget,
      w: 1
    }));
};
