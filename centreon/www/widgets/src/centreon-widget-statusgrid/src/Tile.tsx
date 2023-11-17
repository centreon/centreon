import { Box } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { useTileStyles } from './StatusGrid.styles';
import { ResourceData } from './models';

interface Props {
  data: ResourceData;
  isSmallestSize: boolean;
}

const Tile = ({ isSmallestSize, data }: Props): JSX.Element | null => {
  const { classes } = useTileStyles();
  if (isSmallestSize) {
    return null;
  }

  return (
    <Box className={classes.container} data-status={data.statusName}>
      <EllipsisTypography className={classes.resourceName} textAlign="center">
        {data.name}
      </EllipsisTypography>
      <EllipsisTypography textAlign="center" variant="body2">
        {data.parentName}
      </EllipsisTypography>
    </Box>
  );
};

export default Tile;
