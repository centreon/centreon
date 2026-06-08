import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  basicInputs: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  containerFilter: {
    width: '100%'
  },
  divider: {
    borderStyle: 'dashed',
    margin: theme.spacing(2, 0)
  },
  input: {
    maxWidth: theme.spacing(40)
  },
  sectionColumn: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  sectionsGrid: {
    display: 'grid',
    gap: theme.spacing(2),
    gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))'
  },
  sectionTitle: {
    color: theme.palette.text.secondary,
    fontSize: theme.typography.caption.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    letterSpacing: '0.08em',
    textTransform: 'uppercase'
  }
}));
