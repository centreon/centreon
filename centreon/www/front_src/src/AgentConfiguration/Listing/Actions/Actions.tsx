import { Box } from '@mui/material';
import AddButton from './AddButton';
import Search from './Search';

const Actions = (): JSX.Element => {
  return (
    <Box
      sx={{ display: 'grid', gridTemplateColumns: 'min-content auto', gap: 2 }}
    >
      <AddButton />
      <Search />
    </Box>
  );
};

export default Actions;
