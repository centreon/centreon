import { makeStyles } from 'tss-react/mui';

export const useStyles = makeStyles()((theme) => ({
  areaIndicator: {
    '& .areaIndicator': {
      left: theme.spacing(10)
    },
    '& label:first-of-type': {
      border: '1px dashed #9747FF7F',
      borderRadius: '4px',
      color: '#9747FFFF',
      fontSize: '0.75rem',
      fontWeight: 500,
      left: theme.spacing(1),
      padding: theme.spacing(0.125, 1),
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

    backgroundColor: '#9747FF19',
    minHeight: theme.spacing(4),

    position: 'relative'
  }
}));
