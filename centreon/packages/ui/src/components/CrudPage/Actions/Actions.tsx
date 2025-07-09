import { Box } from '@mui/material';
import { useActionsStyles } from './Actions.styles';
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
  const { classes } = useActionsStyles();

  return (
    <Box className={classes.actions}>
      <AddButton label={labels.add} />
      <div className={classes.filters}>
        <Search label={labels.search} filters={filters} />
      </div>
    </Box>
  );
};

export default Actions;
