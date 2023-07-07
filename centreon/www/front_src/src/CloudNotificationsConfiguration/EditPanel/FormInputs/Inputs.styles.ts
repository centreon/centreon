import { makeStyles } from 'tss-react/mui';

interface StyleProps {
  isExtraFieldHidden: boolean;
}

export const useStyles = makeStyles<StyleProps>()(
  (theme, { isExtraFieldHidden }) => ({
    additionalLabel: {
      color: theme.palette.primary.main,
      fontSize: theme.typography.h6.fontSize,
      fontweight: theme.typography.fontWeightMedium,
      marginBottom: theme.spacing(1),
      marginTop: theme.spacing(1)
    },
    channels: {
      paddingBottom: theme.spacing(1),
      paddingTop: theme.spacing(3)
    },
    divider: {
      background: theme.palette.divider,
      height: theme.spacing(0.125)
    },
    emailTemplateTitle: {
      fontWeight: theme.typography.fontWeightBold
    },
    grid: {
      '& > div:nth-child(3)': {
        marginTop: theme.spacing(4)
      },
      rowGap: theme.spacing(1)
    },
    hostInput: {
      backgroundColor: theme.palette.background.panelGroups,
      borderRadius: theme.spacing(0.5),
      padding: theme.spacing(1.5, 1)
    },
    hostsGrid: {
      rowGap: isExtraFieldHidden ? 0 : theme.spacing(3)
    },
    input: {
      backgroundColor: theme.palette.background.panelGroups,
      borderRadius: theme.spacing(0.5),
      padding: theme.spacing(1)
    }
  })
);
