import { AgentConfigurationListing } from '../../models';

import AddButton from './AddButton';
import InstallationCommandButton from './InstallationCommandButton';
import Search from './Search';

interface Props {
  rows: Array<AgentConfigurationListing>;
}

const Actions = ({ rows }: Props): JSX.Element => {
  return (
    <div className="flex items-center">
      <div className="flex gap-3">
        <AddButton />
        <InstallationCommandButton rows={rows} />
      </div>
      <div className="flex items-center justify-center px-2 w-full">
        <Search />
      </div>
    </div>
  );
};

export default Actions;
