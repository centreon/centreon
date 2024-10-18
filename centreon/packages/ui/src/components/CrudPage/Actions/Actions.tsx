import { Box } from '@mui/material';
import AddButton from './AddButton';
import Search from './Search';

interface Props {
  labels: {
    search: string;
    add: string;
  };
  filters: JSX.Element;
}

const Actions = ({ labels, filters }: Props): JSX.Element => {
  return (
    <Box
      sx={{ display: 'grid', gridTemplateColumns: 'min-content auto', gap: 2 }}
    >
      <AddButton label={labels.add} />
      <Search label={labels.search} filters={filters} />
    </Box>
  );
};

export default Actions;
