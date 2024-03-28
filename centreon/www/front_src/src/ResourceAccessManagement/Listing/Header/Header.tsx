import { Box } from '@mui/system';

import AddButton from './AddButton';
import useHeaderStyles from './Header.styles';

const Header = (): JSX.Element => {
  const { classes } = useHeaderStyles();

  return (
    <Box className={classes.actions}>
      <AddButton />
    </Box>
  );
};

export default Header;
