import { makeStyles } from 'tss-react/mui';

export const useChartGraphStyles = makeStyles()((theme) => ({
  commentContainer: {
    backgroundColor: theme.palette.background.default,
    display: 'flex',
    flexDirection: 'column',
    justifyContent: 'center',
    padding: theme.spacing(1)
  },
  container: {
    backgroundColor: theme.palette.background.paper,
    overflow: 'visible'
  },
  exportToPngButton: {
    display: 'flex',
    justifyContent: 'space-between',
    margin: theme.spacing(0, 1, 1, 2)
  },
  graph: {
    height: '100%',
    margin: 'auto',
    width: '100%'
  },
  graphContainer: {
    display: 'grid',
    gridTemplateRows: '1fr',
    padding: theme.spacing(2, 1, 1)
  },
  graphTabContainer: {
    display: 'grid',
    gridRowGap: theme.spacing(2),
    gridTemplateRows: 'auto 1fr'
  }
}));
