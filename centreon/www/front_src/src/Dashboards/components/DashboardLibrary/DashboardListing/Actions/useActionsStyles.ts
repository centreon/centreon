import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    alignItems : "center",
  },
  favoriteFilter:{
    flex: 1,
    whiteSpace: "nowrap",
    margin: theme.spacing(0,1) ,
    display: "flex",
    justifyContent: "center",
    "& p":{
      fontSize: theme.typography.body2.fontSize,
      fontWeight: theme.typography.fontWeightRegular
    }
  },
  actions: {
    display: 'flex',
    gap: theme.spacing(3),
    flex : 1
  },
  viewMode: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  },
  filter: {
    width: theme.spacing(50)
  },
}));
