import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isHovered: boolean;
}
export const useHostsStyles = makeStyles<StyleProps>()(
  (theme, { isHovered }) => ({
    tooltipContainer: {
      backgroundColor: theme.palette.background.paper,
      color: theme.palette.text.primary,
      padding: 0,
      position: 'relative',
      width: theme.spacing(30),
      minHeight: theme.spacing(15),
      boxShadow: '2px 2px 4px rgba(0, 0, 0, 0.2)'
    },
    content: {
      marginLeft: theme.spacing(3),
      cursor: 'pointer'
    },
    hostCount: {
      color: isHovered
        ? theme.palette.text.primary
        : theme.palette.text.secondary
    }
  })
);

export const useTooltipStyles = makeStyles()((theme) => ({
  header: {
    alignItems: 'center',
    backgroundColor: theme.palette.common.black,
    color: theme.palette.common.white,
    display: 'flex',
    flexDirection: 'column',
    padding: theme.spacing(1),
    width: '100%'
  },
  body: {
    overflowY: 'auto',
    textAlign: 'start'
  }
}));
