import { Box } from '@mui/system';

import useStyle from './Header.styles';
import AddButton from './AddButton';

const Header = (): JSX.Element => {
  const { classes } = useStyle();

  return (
    <Box className={classes.actions}>
      <AddButton />
    </Box>
  );
};

export default Header;
