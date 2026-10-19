import FluidTypography from '.';

interface Props {
  max?: string;
  min?: string;
  pref?: number;
}

export const Default = ({ max, min, pref }: Props): JSX.Element => (
  <FluidTypography max={max} min={min} pref={pref} text="Unreachable" />
);
