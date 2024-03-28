import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  displayTypeContainer: {
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2)
  },
  icon: {
    backgroundColor: 'transparent',
    borderRadius: theme.shape.borderRadius,
    fill: theme.palette.action.disabled,
    height: theme.spacing(8),
    width: theme.spacing(8)
  },
  iconSelected: {
    backgroundColor: theme.palette.background.paper,
    borderRadius: '50%'
  },
  iconWrapper: {
    alignItems: 'center',
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    width: '100%'
  },
  typeOption: {
    '&[data-disabled="true"]': {
      backgroundColor: theme.palette.action.disabledBackground
    },
    backgroundColor: 'transparent',
    height: theme.spacing(10),
    marginRight: theme.spacing(1),
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
