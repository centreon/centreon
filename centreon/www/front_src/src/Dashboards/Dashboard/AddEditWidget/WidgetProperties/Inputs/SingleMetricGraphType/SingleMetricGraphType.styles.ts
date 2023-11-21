import { makeStyles } from 'tss-react/mui';

export const useGraphTypeStyles = makeStyles()((theme) => ({
  graphTypeContainer: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  },
  graphTypeIcon: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center'
  },
  graphTypeOption: {
    '&[data-disabled="true"]': {
      backgroundColor: theme.palette.action.disabledBackground
    },
    backgroundColor: 'transparent',
    height: theme.spacing(10),
    width: theme.spacing(10)
  },
  graphTypeSelected: {
    alignItems: 'center',
    backgroundColor: theme.palette.action.selected,
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    position: 'absolute',
    top: 0,
    width: '100%'
  },
  iconSelected: {
    backgroundColor: theme.palette.background.paper,
    borderRadius: '50%'
  }
}));
