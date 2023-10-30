import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  iconButton: {
    '&[data-size="small"]': {
      '& > svg, & > .MuiSvgIcon-root': {
        fontSize: '1.125rem'
      },
      padding: theme.spacing(0.75)
    }
  }
}));

export { useStyles };
