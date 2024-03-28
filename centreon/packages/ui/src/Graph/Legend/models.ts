export type LegendDirection = 'row' | 'column';

export interface LegendScale {
  domain: Array<number | string>;
  range: Array<string>;
}

export interface LegendProps {
  direction?: LegendDirection;
  scale: LegendScale;
}
