import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { DisplayType } from '../../models';

interface StyleType {
  displayType: DisplayType;
  isSingleChart: boolean;
}

export const useStyles = makeStyles<StyleType>()(
  (_, { displayType, isSingleChart }) => ({
    barStack: {
      height:
        isSingleChart && equals(displayType, DisplayType.Horizontal)
          ? '48%'
          : '96%',
      width:
        isSingleChart && equals(displayType, DisplayType.Vertical)
          ? '48%'
          : '96%'
    },
    container: {
      display: 'flex',
      height: '100%',
      justifyContent: 'center',
      width: '100%'
    },
    pieChart: {
      height: '100%',
      width: '96%'
    }
  })
);
