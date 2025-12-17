import { makeStyles } from 'tss-react/mui';

export const useBaseChartStyles = makeStyles()((theme) => ({
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
  legendContainer: {
    maxWidth: '60%'
  },
  legendContainerVerticalSide: {
    marginRight: theme.spacing(6)
  }
}));
