import { Box } from '@mui/material';

import { useFilterStyles } from '../useActionsStyles';

import PopoverFilter from './PopoverFilter';
import Search from './Filters';

const Filter = (): JSX.Element => {
  const { classes } = useFilterStyles();

  return (
    <Box className={classes.filters}>
      <Search />
      <PopoverFilter />
    </Box>
  );
};

export default Filter;
