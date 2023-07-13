import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  accessRightsStats: {
    '& > span:not(:last-child)::after': {
      content: '", "'
    },
    alignSelf: 'flex-end',
    color: theme.palette.text.secondary,
    fontSize: '0.75rem',
    height: '1.5rem',
    lineHeight: '1.5rem',

    padding: theme.spacing(1, 0)
  }
}));

export { useStyles };
