import { alpha } from '@mui/material';

const commonTickLabelProps = {
  fontFamily: 'Roboto, sans-serif',
  fontSize: 10,
  textAnchor: 'middle'
};

const margin = { bottom: 30, left: 55, right: 55, top: 30 };

interface FillColor {
  areaColor: string;
  transparency: number;
}

const getFillColor = ({
  transparency,
  areaColor
}: FillColor): string | undefined =>
  transparency ? alpha(areaColor, 1 - transparency * 0.01) : undefined;

const dateFormat = 'L';
const timeFormat = 'LT';
const dateTimeFormat = `${dateFormat} ${timeFormat}`;
const maxLinesDisplayedLegend = 11;

export {
  commonTickLabelProps,
  margin,
  getFillColor,
  dateFormat,
  timeFormat,
  dateTimeFormat,
  maxLinesDisplayedLegend
};
