import { alpha } from '@mui/material';

const commonTickLabelProps = {
  fontFamily: 'Roboto, sans-serif',
  fontSize: 10
};

const margin = { bottom: 30, left: 45, right: 45, top: 30 };

interface FillColor {
  areaColor: string;
  transparency: number;
}

const getFillColor = ({
  transparency,
  areaColor
}: FillColor): string | undefined =>
  transparency ? alpha(areaColor, 1 - transparency * 0.01) : undefined;

export { commonTickLabelProps, margin, getFillColor };
