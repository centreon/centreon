import { Box, Typography, TypographyProps } from '@mui/material';

const EllipsisTypography = (props: TypographyProps): JSX.Element => {
  return (
    <Box sx={{ width: '100%' }}>
      <Typography
        sx={{
          overflow: 'hidden',
          textOverflow: 'ellipsis',
          whiteSpace: 'nowrap'
        }}
        {...props}
      />
    </Box>
  );
};

export default EllipsisTypography;
