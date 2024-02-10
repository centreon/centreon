import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { LegendDirection } from '../Legend/models';

export const useBarStackStyles = makeStyles<{
  legendDirection: LegendDirection;
}>()((theme, { legendDirection }) => ({
  container: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: equals(legendDirection, 'column') ? 'row' : 'column',
    gap: theme.spacing(3),
    justifyContent: 'center',
    minWidth: theme.spacing(50),
    padding: theme.spacing(2)
  },
  svgContainer: {
    alignItems: 'center',
    backgroundColor: theme.palette.background.panelGroups,
    borderRadius: '5px',
    display: 'flex',
    justifyContent: 'center'
  },
  svgWrapper: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(2)
  },
  title: {
    fontSize: theme.typography.h6.fontSize,
    fontWeight: theme.typography.fontWeightBold
  }
}));
