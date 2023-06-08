import { Box } from '@mui/material';

import { LoadingSkeleton } from '@centreon/ui';

const Skeleton = (): JSX.Element => (
  <Box sx={{ display: 'flex', flexDirection: 'column', rowGap: 1 }}>
    <LoadingSkeleton height={50} />
    <LoadingSkeleton height={50} />
    <LoadingSkeleton height={50} />
  </Box>
);

export default Skeleton;
