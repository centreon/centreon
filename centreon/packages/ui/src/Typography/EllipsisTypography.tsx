import { forwardRef, type ForwardedRef } from 'react';

import { Box, Typography, type TypographyProps } from '@mui/material';

const EllipsisTypography = forwardRef(
  (
    { containerClassname, ...props }: TypographyProps & { containerClassname?: string },
    ref?: ForwardedRef<HTMLSpanElement>) => {
    return (
      <Box className={containerClassname} sx={{ width: '100%' }}>
        <Typography
          ref={ref}
          sx={{
            overflow: 'hidden',
            textOverflow: 'ellipsis',
            whiteSpace: 'nowrap'
          }}
          {...props}
        />
      </Box>
    );
  }
);

export default EllipsisTypography;
