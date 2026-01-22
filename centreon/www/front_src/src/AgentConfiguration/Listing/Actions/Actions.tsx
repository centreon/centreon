import { useActionsStyles } from './Actions.styles';
import AddButton from './AddButton';
import Search from './Search';

const Actions = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.container}>
      <AddButton />
      <div className={classes.searchBar}>
        <Search />
      </div>
    </div>
  );
};
export default Actions;
