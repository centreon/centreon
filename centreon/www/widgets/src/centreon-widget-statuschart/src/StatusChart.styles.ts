import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { DisplayType } from './models';

export const useStyles = makeStyles<{ displayType: DisplayType }>()(
  (_, { displayType }) => ({
    container: {
      display: 'flex',
      flexDirection: equals(displayType, DisplayType.Horizontal)
        ? 'column'
        : 'row',
      height: '100%',
      width: '100%'
    }
  })
);
