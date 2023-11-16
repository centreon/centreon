import { Box } from '@mui/material';

import { EllipsisTypography } from '@centreon/ui';

import { useTileStyles } from './StatusGrid.styles';
import { ResourceData } from './models';

interface Props {
  data: ResourceData;
  isSmallestSize: boolean;
}

const Tile = ({
  isSmallestSize,
  data: { name, parentName }
}: Props): JSX.Element | null => {
  const { classes } = useTileStyles();
  if (isSmallestSize) {
    return null;
  }

  return (
    <Box className={classes.container}>
      <EllipsisTypography className={classes.resourceName} textAlign="center">
        {name}
      </EllipsisTypography>
      <EllipsisTypography textAlign="center" variant="body2">
        {parentName}
      </EllipsisTypography>
    </Box>
  );
};

export default Tile;
