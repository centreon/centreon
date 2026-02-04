import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  windowHeight: number;
}

const actionsHeight = 36;

export const useDurationstyles = makeStyles()((theme) => ({
  container: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  }
}));

export const useInputCalenderStyles = makeStyles<StyleProps>()(
  (theme, { windowHeight }) => ({
    containerDatePicker: {
      alignItems: 'center',
      display: 'flex',
      gap: theme.spacing(1)
    },
    dateTimePicker: {
      '& .MuiInputBase-input': {
        width: '100%'
      },
      flex: 0.9
    },
    error: {
      paddingLeft: theme.spacing(6.5),
      textAlign: 'start'
    },
    picker: {
      '& .MuiInputBase-root': {
        '& .MuiInputBase-input': {
          width: '100%'
        },
        backgroundColor: theme.palette.background.default,
        width: theme.spacing(34.5)
      }
    },
    popper: {
      height: (windowHeight - actionsHeight) / 2,
      overflow: 'auto'
    },
    popperContainer: {
      '& .MuiPaper-root': {
        backgroundColor: theme.palette.background.default
      },
      zIndex: theme.zIndex.tooltip
    },
    root: {
      '> div': {
        '& div:nth-of-type(1)': {
          '> div': {
            '& .MuiDateCalendar-root': {
              height: theme.spacing(38.5)
            },
            '& .MuiMultiSectionDigitalClock-root': {
              maxHeight: theme.spacing(38.5)
            }
          }
        }
      }
    },
    secondaryContainer: {
      padding: theme.spacing(0, 0.5)
    },
    subContainer: {
      flex: 0.1,
      paddingLeft: theme.spacing(0.5)
    }
  })
);
