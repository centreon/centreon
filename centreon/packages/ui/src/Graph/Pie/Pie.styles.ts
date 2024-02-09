import { makeStyles } from 'tss-react/mui';
import { equals } from 'ramda';

import { LegendDirection } from '../Lengend/models';

export const usePieStyles = makeStyles<{ legendDirection: LegendDirection }>()(
  (theme, { legendDirection }) => ({
    container: {
      alignItems: 'center',
      display: 'flex',
      flexDirection: equals(legendDirection, 'row') ? 'column' : 'row',
      gap: theme.spacing(3),
      padding: theme.spacing(3)
    },
    pieTitle: {
      fontSize: theme.typography.h6.fontSize,
      fontWeight: theme.typography.fontWeightBold,
      marginBottom: theme.spacing(2)
    },
    svgContainer: {
      alignItems: 'center',
      backgroundColor: theme.palette.background.panelGroups,
      borderRadius: '100%',
      display: 'flex',
      justifyContent: 'center'
    }
  })
);
