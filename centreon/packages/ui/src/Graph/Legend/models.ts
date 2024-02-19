export type LegendDirection = 'row' | 'column';

export interface LegendConfiguration {
  direction: LegendDirection;
}

interface LegendScale {
  domain: Array<number | string>;
  range: Array<string>;
}

export interface LegendProps {
  configuration?: LegendConfiguration;
  scale: LegendScale;
}
