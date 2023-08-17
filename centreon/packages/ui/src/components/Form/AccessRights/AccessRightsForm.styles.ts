import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  accessRightsForm: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(6),

    maxWidth: '520px',
    paddingTop: theme.spacing(3),

    width: '100%'
  },
  accessRightsFormList: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  }
}));

export { useStyles };
