import { makeStyles } from 'tss-react/mui';

export const usePanelHeaderStyles = makeStyles()((theme) => ({
  panelActionsIcons: {
    columnGap: theme.spacing(2),
    display: 'flex',
    flexDirection: 'row',
    marginRight: theme.spacing(1)
  },
  panelHeader: {
    padding: theme.spacing(0)
  }
}));

export const useAddWidgetPanelStyles = makeStyles()((theme) => ({
  addWidgetPanel: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'column',
    height: '100%',
    justifyContent: 'space-evenly',
    margin: theme.spacing(1, 2)
  },
  avatar: {
    alignSelf: 'center',
    backgroundColor: theme.palette.primary.main,
    height: theme.spacing(10),
    width: theme.spacing(10)
  }
}));
