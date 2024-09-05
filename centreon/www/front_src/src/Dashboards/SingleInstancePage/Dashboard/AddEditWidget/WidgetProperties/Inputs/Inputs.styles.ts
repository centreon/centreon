import { makeStyles } from 'tss-react/mui';

export const useResourceStyles = makeStyles()((theme) => ({
  resourceComposition: {
    overflow: 'auto',
    paddingTop: theme.spacing(1),
    width: '100%'
  },
  resourceCompositionItem: {
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(20)} 1fr`
  },
  resourceTitle: {
    lineHeight: 1,
    paddingBottom: theme.spacing(0.5)
  },
  resourceType: {
    borderRadius: `${theme.shape.borderRadius}px 0px 0px ${theme.shape.borderRadius}px`
  },
  resources: {
    '& .MuiInputBase-root': {
      borderRadius: `0px ${theme.shape.borderRadius}px ${theme.shape.borderRadius}px 0px`
    }
  },
  resourcesContainer: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  },
  resourcesHeader: {
    display: 'flex',
    gap: theme.spacing(1),
    width: '100%'
  },
  resourcesHeaderDivider: {
    alignSelf: 'center',
    flexGrow: 1
  },
  subtitle: {
    marginBottom: theme.spacing(0.5)
  },
  warningText: {
    color: theme.palette.warning.main
  }
}));

export const useSwitchStyles = makeStyles()((theme) => ({
  switch: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  }
}));

export const useRefreshIntervalStyles = makeStyles()((theme) => ({
  customInterval: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  customIntervalField: {
    width: theme.spacing(10)
  }
}));

export const useThresholdStyles = makeStyles()((theme) => ({
  customField: {
    width: theme.spacing(20)
  },
  customThreshold: {
    alignItems: 'center',
    display: 'flex',
    flexDirection: 'row',
    gap: theme.spacing(1)
  },
  showThreshold: {
    marginBottom: theme.spacing(1)
  },
  thresholds: {
    display: 'flex',
    flexDirection: 'column',
    gap: theme.spacing(1)
  }
}));

export const useTextFieldStyles = makeStyles<{ hasMarginBottom: boolean }>()(
  (theme, { hasMarginBottom }) => ({
    container: {
      display: 'flex',
      flexDirection: 'column',
      gap: theme.spacing(0.5),
      marginBottom: hasMarginBottom ? theme.spacing(0.5) : 0
    }
  })
);
