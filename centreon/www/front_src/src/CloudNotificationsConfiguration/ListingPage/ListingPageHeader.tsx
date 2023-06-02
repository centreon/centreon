import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import Filter from '../Filter';
import Title from '../Title';

const useStyle = makeStyles()((theme) => ({
  box: {
    alignItems: 'center',
    display: 'flex',
    justifyContent: 'space-between',
    padding: theme.spacing(1, 0)
  }
}));

const ListingPageHeader = (): JSX.Element => {
  const { classes } = useStyle();

  return (
    <Box className={classes.box}>
      <Title />
      <Filter />
    </Box>
  );
};

export default ListingPageHeader;
