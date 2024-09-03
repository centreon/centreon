import { Box, Typography, TypographyProps } from '@mui/material';

const EllipsisTypography = ({
  containerClassname,
  ...props
}: TypographyProps & { containerClassname?: string }): JSX.Element => {
  return (
    <Box className={containerClassname} sx={{ width: '100%' }}>
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
