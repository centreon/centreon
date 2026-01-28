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
    width: '95%'
  },
  spacing: {
    margin: theme.spacing(0, 2, 0, 0)
  },
  subContainer: {
    alignItems: 'center',
    display: 'flex',
    gap: 1,
    flex: 0.1
  },
  subContainerSearch: {
    alignItems: 'center',
    display: 'flex',
    flex: 0.9,
    flexDirection: 'row',
    gap: theme.spacing(0.5),
    justifyContent: 'center'
  }
}));
