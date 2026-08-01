import { makeStyles } from 'tss-react/mui';

export const useActionsStyles = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    display: 'grid',
    gap: theme.spacing(3),
    gridTemplateColumns: '1fr auto 1fr',
    width: '100%'
  },
  filter: {
    justifySelf: 'center',
    width: theme.spacing(70)
  },
  leftCluster: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(2),
    justifySelf: 'start'
  },
  spacer: {},
  viewMode: {
    '& [data-selected="true"]': {
      backgroundColor: theme.palette.background.paper
    },
    alignItems: 'center',
    backgroundColor: theme.palette.action.hover,
    borderRadius: theme.shape.borderRadius,
    display: 'flex',
    gap: theme.spacing(0.25),
    padding: theme.spacing(0.25)
  }
}));
