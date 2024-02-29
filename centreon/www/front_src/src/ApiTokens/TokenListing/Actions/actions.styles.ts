import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  buttonCreationToken: {
    marginRight: theme.spacing(1),
    minWidth: theme.spacing(15)
  },
  container: {
    display: 'flex',
    flexDirection: 'row',
    flexWrap: 'wrap',
    rowGap: theme.spacing(0.5)
  },
  popoverMenu: {
    zIndex: theme.zIndex.modal
  },
  search: {
    minWidth: theme.spacing(100)
  },
  spacing: {
    margin: theme.spacing(0, 2, 0, 0)
  },
  subContainer: {
    alignItems: 'center',
    display: 'flex'
  },
  subContainerSearch: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    flexWrap: 'wrap',
    rowGap: theme.spacing(0.5)
  }
}));
