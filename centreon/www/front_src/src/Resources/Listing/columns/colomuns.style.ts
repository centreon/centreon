import { makeStyles } from 'tss-react/mui';

const useColumnStyles = makeStyles()((theme) => ({
  extraSmallChip: {
    height: theme.spacing(1.25),
    lineHeight: theme.spacing(1.25),
    minWidth: theme.spacing(1.25)
  },
  resourceDetailsCell: {
    alignItems: 'center',
    display: 'flex',
    flexWrap: 'nowrap'
  },
  resourceNameItem: {
    lineHeight: 1,
    whiteSpace: 'nowrap'
  },
  resourceNameText: {
    paddingLeft: theme.spacing(0.5)
  },
  statusChip: {
    marginRight: theme.spacing(0.5)
  }
}));

export default useColumnStyles;
