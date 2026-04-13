import { AgentConfigurationListing } from '../../models';
import { useActionsStyles } from './Actions.styles';
import AddButton from './AddButton';
import InstallationCommandButton from './InstallationCommandButton';
import Search from './Search';

interface Props {
  rows: Array<AgentConfigurationListing>;
}

const Actions = ({ rows }: Props): JSX.Element => {
  const { classes } = useActionsStyles();

  return (
    <div className={classes.container}>
      <div className="flex gap-3">
        <AddButton />
        <InstallationCommandButton rows={rows} />
      </div>
      <div className={classes.searchBar}>
        <Search />
      </div>
    </div>
  );
};
export default Actions;
