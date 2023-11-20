import TokenCreationButton from '../../tokenCreation';

import Refresh from './refresh';
import Search from './search';

const Actions = (): JSX.Element => {
  return (
    <>
      <TokenCreationButton />
      <Refresh />
      <Search />
    </>
  );
};
export default Actions;
