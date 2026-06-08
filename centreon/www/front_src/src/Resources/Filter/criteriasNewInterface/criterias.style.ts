import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  bridge: {
    borderStyle: 'solid'
  },
  column: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1.5),
    minWidth: 0
  },
  columns: {
    display: 'grid',
    gap: theme.spacing(4),
    gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
    width: '100%'
  },
  columnTitle: {
    color: theme.palette.text.secondary,
    fontSize: theme.typography.caption.fontSize,
    fontWeight: theme.typography.fontWeightBold,
    letterSpacing: '0.08em',
    textTransform: 'uppercase'
  },
  containerDivider: {
    display: 'flex',
    margin: theme.spacing(0, 2)
  },
  containerFilter: {
    width: '100%'
  },
  div: {
    marginTop: theme.spacing(1)
  },
  divider: {
    borderStyle: 'solid',
    margin: theme.spacing(1.5, 0, 0, 0)
  },
  dividerInputs: {
    margin: theme.spacing(1, 0),
    opacity: 0
  },
  extended: {
    display: 'flex',
    flexDirection: 'row',
    width: '100%'
  },
  footer: {
    borderStyle: 'solid',
    marginTop: theme.spacing(1)
  },
  inputInformation: {
    backgroundColor: theme.palette.background.default,
    minWidth: theme.spacing(40)
  },
  moreFiltersButton: {
    alignSelf: 'flex-end',
    display: 'flex',
    marginBottom: theme.spacing(1)
  },
  small: {
    // Rounded select inputs to match the search bar / chips aesthetic.
    '& .MuiOutlinedInput-root': {
      borderRadius: theme.spacing(1.25)
    },
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(2),
    width: '100%'
  }
}));
