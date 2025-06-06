import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isExpired: boolean;
  isHovered: boolean;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { isHovered, isExpired }) => ({
    container: {
      paddingLeft: theme.spacing(0.5),
      color: isExpired
        ? theme.palette.error.main
        : isHovered
          ? theme.palette.text.primary
          : theme.palette.text.secondary,
      fontSize: theme.typography.body2.fontSize
    }
  })
);

export default useStyles;
