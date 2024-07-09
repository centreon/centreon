import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  container: {
    alignItems: 'center',
    flexWrap: 'nowrap'
  },
  extraMargin: {
    marginRight: theme.spacing(0.5)
  },
  gridItem: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row'
  },
  iconButton: {
    padding: theme.spacing(0, 0.25)
  },
  large: {
    flex: 1
  },
  medium: {
    justifyContent: 'space-between'
  },
  text: {
    marginRight: theme.spacing(0.5)
  },
  tooltipClassName: {
    position: 'relative',
    top: theme.spacing(-0.5)
  },
  visualizationContainer: {
    alignItems: 'center',
    flexWrap: 'nowrap',
    justifyContent: 'center'
  }
}));
