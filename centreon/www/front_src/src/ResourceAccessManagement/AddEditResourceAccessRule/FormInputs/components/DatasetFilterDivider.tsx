import { ReactElement } from 'react';

import { Chip, Divider } from '@mui/material';
import { Add } from '@mui/icons-material';

import { useDatasetFilterDividerStyles } from '../styles/DatasetFilterDivider.styles';

const AddIcon = (): ReactElement => {
  const { classes } = useDatasetFilterDividerStyles();

  return <Add className={classes.addIcon} fontSize="small" />;
};

const DatasetFilterDivider = (): ReactElement => {
  const { classes } = useDatasetFilterDividerStyles();

  return (
    <Divider className={classes.divider} variant="middle">
      <Chip icon={<AddIcon />} />
    </Divider>
  );
};

export default DatasetFilterDivider;
