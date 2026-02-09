import { useActionsStyles } from './Actions.styles';
import AddButton from './AddButton';
import InstallationCommandButton from './InstallationCommandButton';
import Search from './Search';

const Actions = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.container}>
      <div className="flex gap-3">
        <AddButton />
        <InstallationCommandButton />
      </div>
      <div className={classes.searchBar}>
        <Search />
      </div>
    </div>
  );
};
export default Actions;
