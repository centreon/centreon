import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  groupLabel: {
    backgroundColor: theme.palette.chip.color.neutral,
    borderRadius: '0.1875rem',
    color: theme.palette.primary.contrastText,
    fontSize: '0.5625rem',
    fontWeight: 500,
    letterSpacing: '0.05rem',
    marginLeft: theme.spacing(1),
    padding: theme.spacing(0.125, 0.5),
    textTransform: 'uppercase',
    verticalAlign: 'middle'
  }
}));

export { useStyles };
