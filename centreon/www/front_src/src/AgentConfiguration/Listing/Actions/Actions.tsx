import AddButton from './AddButton';
import Search from './Search';

import { useActionsStyles } from './Actions.styles';

const Actions = (): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.container}>
      <div className={classes.actions}>
        <AddButton />
      </div>
      <div className={classes.searchBar}>
        <Search />
      </div>
    </div>
  );
};
export default Actions;
