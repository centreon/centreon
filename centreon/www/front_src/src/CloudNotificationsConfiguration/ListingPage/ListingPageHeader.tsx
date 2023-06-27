import { makeStyles } from 'tss-react/mui';

import { Box } from '@mui/material';

import Filter from '../Filter';
import Title from '../Title';

const useStyle = makeStyles()((theme) => ({
  box: {
    alignItems: 'center',
    borderBottom: `1px solid ${theme.palette.primary.main}`,
    display: 'flex',
    justifyContent: 'space-between'
  },
  title: {
    borderBottom: 'none'
  }
}));

const ListingPageHeader = (): JSX.Element => {
  const { classes } = useStyle();

  return (
    <Box className={classes.box}>
      <Title className={classes.title} />
      <Filter />
    </Box>
  );
};

export default ListingPageHeader;
