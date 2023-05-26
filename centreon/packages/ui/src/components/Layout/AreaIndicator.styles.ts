import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  areaIndicator: {
    '& .areaIndicator': {
      left: theme.spacing(10)
    },
    '& label:first-of-type': {
      fontSize: '0.75rem',
      fontWeight: 500,
      left: theme.spacing(1),
      position: 'absolute',
      top: theme.spacing(0.75)
    },
    '&[data-depth="1"]': {
      '& label:first-of-type': {
        '&:before': {
          content: '"+ "'
        },
        left: theme.spacing(10)
      }
    },

    backgroundColor: 'rgba(151, 71, 255, .1)',

    // border: '1.5px dashed rgba(151, 71, 255, .5)',
    borderRadius: '4px',

    // boxSizing: 'content-box',
    color: 'rgba(151, 71, 255, 1)',

    minHeight: theme.spacing(4),

    position: 'relative'
  }
}));
