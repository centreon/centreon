import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center'
  },
  condensed: {
    marginRight: theme.spacing(2)
  },
  container: {
    '& .MuiButton-root': {
      backgroundColor: 'transparent',
      boxShadow: theme.spacing(0, 0)
    },
    backgroundColor: theme.palette.primary.main
  },
  disabled: {
    backgroundColor: theme.palette.action.disabledBackground
  },
  iconArrow: {
    color: theme.palette.background.paper
  }
}));

export const useOptionsStyles = makeStyles()((theme) => ({
  button: {
    alignItems: 'flex-start'
  },
  container: {
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    flexDirection: 'column',
    minWidth: theme.spacing(13.5),
    padding: theme.spacing(0, 1)
  },
  icon: {
    minWidth: theme.spacing(3)
  },
  itemText: {
    '& .MuiTypography-root': {
      width: '100%'
    },
    display: 'flex',
    flexDirection: 'row-reverse',
    margin: 0,
    paddingRight: theme.spacing(0.5)
  },
  popover: {
    zIndex: theme.zIndex.fab
  }
}));

export const useIconArrowStyles = makeStyles()({
  container: {
    display: 'flex'
  },
  reverseIcon: {
    transform: 'rotate(180deg)'
  }
});

export const useTextStyles = makeStyles()((theme) => ({
  description: {
    fontSize: '0.8rem',
    maxWidth: theme.spacing(31)
  },
  title: {
    color: theme.palette.info.main,
    fontWeight: 'bold'
  }
}));
