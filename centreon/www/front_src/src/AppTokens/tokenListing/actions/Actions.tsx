import TokenCreationButton from '../../tokenCreation';

import { useStyles } from './actions.styles';
import Search from './search';
import TokenFilter from './search/filter';

interface Props {
  refresh: React.ReactNode;
}

const Actions = ({ refresh }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <div className={classes.subContainerSearch}>
        <Search />
        <TokenFilter />
      </div>
      <div>
        <TokenCreationButton />
        {refresh}
      </div>
    </div>
  );
};
export default Actions;
