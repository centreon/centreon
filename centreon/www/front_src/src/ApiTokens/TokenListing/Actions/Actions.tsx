import TokenFilter from './Filters';
import { useStyles } from './actions.styles';

import Refresh from './Refresh';

interface Props {
  buttonCreateToken: React.ReactNode;
}

const Actions = ({ buttonCreateToken }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      <div className={classes.subContainer}>
        {buttonCreateToken}
        <Refresh />
      </div>

      <div className={classes.subContainerSearch}>
        <TokenFilter />
      </div>
    </div>
  );
};
export default Actions;
