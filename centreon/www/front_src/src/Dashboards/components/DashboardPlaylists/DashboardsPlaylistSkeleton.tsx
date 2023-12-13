import { Box, Skeleton } from '@mui/material';

const DashboardsPlaylistSkeleton = (): JSX.Element => {
  return (
    <Box>
      <Skeleton />
      <Skeleton animation="wave" />
      <Skeleton animation={false} />
    </Box>
  );
};

export default DashboardsPlaylistSkeleton;
