import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { ThemeMode } from '@centreon/ui-context';

interface StyleProps {
  isExtraFieldHidden?: boolean;
}

export const useStyles = makeStyles<StyleProps>()(
  (theme, { isExtraFieldHidden }) => ({
    additionalLabel: {
      color: theme.palette.primary.main,
      fontSize: theme.typography.subtitle1.fontSize,
      fontWeight: theme.typography.fontWeightMedium,
      marginBottom: theme.spacing(0.5),
      marginTop: theme.spacing(0.5)
    },
    channels: {
      paddingBottom: theme.spacing(1),
      paddingTop: theme.spacing(3)
    },
    editorToolbar: {
      flexWrap: 'wrap'
    },
    emailTemplateTitle: {
      fontWeight: theme.typography.fontWeightBold
    },
    grid: {
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
    },
    textEditor: {
      backgroundColor: equals(theme.palette.mode, ThemeMode.dark)
        ? theme.palette.background.default
        : theme.palette.common.white
    },
    timeperiod: {
      alignItems: 'center',
      display: 'flex',
      gap: theme.spacing(2)
    },
    timeperiodTooltip: {
      fontSize: theme.spacing(2.75)
    },
    titleGroup: {
      fontWeight: theme.typography.fontWeightBold
    }
  })
);
