import { makeStyles } from 'tss-react/mui';

const useStyles = makeStyles()((theme) => ({
  container: {
    '& .visx-axis-bottom': {
      '& .visx-axis-tick': {
        '& .visx-line': {
          stroke: theme.palette.text.primary
        },
        text: {
          fill: theme.palette.text.primary
        }
      }
    },
    '& .visx-axis-line': {
      stroke: theme.palette.text.primary
    },
    '& .visx-axis-right': {
      '& .visx-axis-tick': {
        '& .visx-line': {
          stroke: theme.palette.text.primary
        }
      }
    },
    '& .visx-columns': {
      '& .visx-line': {
        stroke: theme.palette.divider
      }
    },
    '& .visx-rows': {
      '& .visx-line': {
        stroke: theme.palette.divider
      }
    },
    fill: theme.palette.text.primary,
    position: 'relative'
  },
  header: {
    display: 'grid',
    gridTemplateColumns: '0.4fr 1fr 0.4fr',
    width: '100%'
  }
}));

export { useStyles };
