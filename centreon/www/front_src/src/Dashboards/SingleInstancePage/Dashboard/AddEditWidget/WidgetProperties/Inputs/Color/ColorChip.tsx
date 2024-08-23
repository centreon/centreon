import { Box } from '@mui/material';

interface Props {
  color: string;
}

const ColorChip = ({ color }: Props): JSX.Element => {
  return (
    <Box
      data-testid={`color-chip-${color}`}
      sx={{
        backgroundColor: color,
        borderRadius: '50%',
        height: 16,
        width: 16
      }}
    />
  );
};

export default ColorChip;
