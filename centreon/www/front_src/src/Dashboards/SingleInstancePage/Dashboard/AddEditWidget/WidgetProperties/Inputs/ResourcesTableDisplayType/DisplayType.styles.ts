import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  },
  iconSelected: {
    backgroundColor: theme.palette.background.paper,
    borderRadius: '50%'
  },
  typeIcon: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center'
  },
  typeOption: {
    '&[data-disabled="true"]': {
      backgroundColor: theme.palette.action.disabledBackground
    },
    backgroundColor: 'transparent',
    height: theme.spacing(10),
    width: theme.spacing(10)
  },
  typeSelected: {
    alignItems: 'center',
    backgroundColor: theme.palette.action.selected,
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    position: 'absolute',
    top: 0,
    width: '100%'
  }
}));
