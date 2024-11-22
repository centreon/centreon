import { makeStyles } from 'tss-react/mui';

import { alpha } from '@mui/system';

export const useColorSelectorStyle = makeStyles()((theme) => ({
  colors: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(0.5),
    padding: theme.spacing(0.5)
  },
  popover: {
    boxShadow: theme.shadows[3],
    zIndex: theme.zIndex.tooltip
  },
  selectedColor: {
    backgroundColor: alpha(theme.palette.primary.main, 0.3)
  },
  selectorContainer: {
    height: theme.spacing(3.5),
    width: theme.spacing(7)
  },
  selectorContent: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    height: '100%',
    justifyContent: 'center'
  }
}));
