import { makeStyles } from 'tss-react/mui';

export const useTooltipStyles = makeStyles()((theme) => ({
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
    minHeight: theme.spacing(10),
    overflowY: 'auto',
    padding: theme.spacing(0, 1, 1),
    textAlign: 'start'
  }
}));
