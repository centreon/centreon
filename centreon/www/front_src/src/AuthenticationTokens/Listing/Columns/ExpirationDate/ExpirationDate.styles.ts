import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isExpired: boolean;
  isHovered: boolean;
}

const useStyles = makeStyles<StyleProps>()(
  (theme, { isHovered, isExpired }) => ({
    container: {
      color: isExpired
        ? theme.palette.error.main
        : isHovered
          ? theme.palette.text.primary
          : theme.palette.text.secondary,
      fontSize: theme.typography.body2.fontSize,
      paddingLeft: theme.spacing(0.5)
    }
  })
);

export default useStyles;
