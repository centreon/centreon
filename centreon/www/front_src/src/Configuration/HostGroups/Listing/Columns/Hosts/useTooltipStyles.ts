import { makeStyles } from 'tss-react/mui';

export const useTooltipStyles = makeStyles()((theme) => ({
  tooltip: {
    backgroundColor: theme.palette.background.paper,
    color: theme.palette.text.primary,
    padding: 0,
    position: 'relative',
    width: theme.spacing(30),
    boxShadow: '2px 2px 4px rgba(0, 0, 0, 0.2)'
  },
  body: {
    padding: theme.spacing(1),
    textAlign: 'center'
  },
  header: {
    alignItems: 'center',
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white,
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(1),
    width: '100%'
  },
  listContainer: {
    maxHeight: theme.spacing(25),
    overflowY: 'auto',
    padding: theme.spacing(0, 1, 1),
    textAlign: 'start'
  },
  content: {
    marginLeft: theme.spacing(3)
  }
}));
