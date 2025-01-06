import { type MouseEvent, type MutableRefObject, useState } from 'react';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { makeStyles } from 'tss-react/mui';

import LaunchIcon from '@mui/icons-material/Launch';
import SaveAsImageIcon from '@mui/icons-material/SaveAlt';
import {
  Divider,
  Menu,
  MenuItem,
  Typography,
  alpha,
  useTheme
} from '@mui/material';

import {
  ContentWithCircularLoading,
  IconButton,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import FederatedComponent from '../../../components/FederatedComponents';
import { selectedResourceDetailsEndpointDerivedAtom } from '../../Details/detailsAtoms';
import type { ResourceDetails } from '../../Details/models';
import type { TimelineEvent } from '../../Details/tabs/Timeline/models';
import memoizeComponent from '../../memoizedComponent';
import { type Resource, ResourceType } from '../../models';
import {
  labelCSV,
  labelExport,
  labelExportAs,
  labelPNGAsDisplayed,
  labelPNGMediumSize,
  labelPNGSmallSize,
  labelPerformancePage
} from '../../translatedLabels';

import exportToPng from './ExportableGraphWithTimeline/exportToPng';

interface Props {
  end: string;
  performanceGraphRef?: MutableRefObject<HTMLDivElement>;
  resource?: Resource | ResourceDetails;
  start: string;
  timeline?: Array<TimelineEvent>;
}

const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    paddingRight: theme.spacing(1),
    justifyContent: 'flex-end'
  },
  menu: {
    width: theme.spacing(22)
  },
  menuHeader: {
    fontWeight: theme.typography.fontWeightBold,
    color: theme.palette.primary.main
  },
  menuItem: {
    fontWeight: theme.typography.fontWeightRegular,
    color: alpha(theme.palette.text.primary, 0.7)
  },
  exportAs: {
    cursor: 'auto',
    '&:hover': {
      backgroundColor: 'transparent'
    }
  }
}));

const GraphActions = ({
  resource,
  timeline,
  performanceGraphRef,
  end,
  start
}: Props): JSX.Element | null => {
  const { classes } = useStyles();
  const theme = useTheme();
  const { t } = useTranslation();
  const [menuAnchor, setMenuAnchor] = useState<Element | null>(null);
  const [exporting, setExporting] = useState<boolean>(false);

  const { format } = useLocaleDateTimeFormat();
  const navigate = useNavigate();

  const resourceDetailsEndPoint = useAtomValue(
    selectedResourceDetailsEndpointDerivedAtom
  );

  const openSizeExportMenu = (event: MouseEvent<HTMLButtonElement>): void => {
    setMenuAnchor(event.currentTarget);
  };
  const closeSizeExportMenu = (): void => {
    setMenuAnchor(null);
  };

  const graphToCsvEndpoint = `${resource?.links?.endpoints.performance_graph}/download?start_date=${start}&end_date=${end}`;

  const exportToCsv = (): void => {
    window.open(graphToCsvEndpoint, 'noopener', 'noreferrer');
  };

  const goToPerformancePage = (): void => {
    const startTimestamp = format({
      date: start,
      formatString: 'X'
    });
    const endTimestamp = format({
      date: end,
      formatString: 'X'
    });
    const svcId =
      resource?.type === ResourceType.metaservice
        ? `_Module_Meta;meta_${resource?.id}`
        : `${resource?.parent?.name};${resource?.name}`;

    const urlParameters = (): string => {
      const params = new URLSearchParams({
        end: endTimestamp,
        mode: '0',
        start: startTimestamp,
        svc_id: svcId
      });

      return params.toString();
    };

    navigate(`/main.php?p=204&${urlParameters()}`);
  };

  const convertToPng = (ratio: number): void => {
    if (!performanceGraphRef) {
      return;
    }
    setMenuAnchor(null);
    setExporting(true);
    exportToPng({
      backgroundColor: theme.palette.background.paper,
      element: performanceGraphRef.current as HTMLElement,
      ratio,
      title: `${resource?.name}-performance`
    }).finally(() => {
      setExporting(false);
    });
  };

  return (
    <div className={classes.buttonGroup}>
      <ContentWithCircularLoading
        alignCenter={false}
        loading={exporting}
        loadingIndicatorSize={16}
      >
        <>
          <IconButton
            disableTouchRipple
            ariaLabel={t(labelPerformancePage) as string}
            color="primary"
            data-testid={labelPerformancePage}
            size="small"
            title={t(labelPerformancePage) as string}
            onClick={goToPerformancePage}
          >
            <LaunchIcon fontSize="inherit" />
          </IconButton>
          <IconButton
            disableTouchRipple
            ariaLabel={t(labelExport) as string}
            data-testid={labelExport}
            disabled={isNil(timeline)}
            size="small"
            title={t(labelExport) as string}
            onClick={openSizeExportMenu}
          >
            <SaveAsImageIcon fontSize="inherit" />
          </IconButton>
          <FederatedComponent
            path="/anomaly-detection/configuration-button"
            styleMenuSkeleton={{ height: 2.5, width: 2.25 }}
            type={resource?.type}
          />
          <FederatedComponent
            end={end}
            path="/anomaly-detection/modal"
            resourceEndpoint={resourceDetailsEndPoint}
            start={start}
            styleMenuSkeleton={{ height: 0, width: 0 }}
            type={resource?.type}
          />
          <Menu
            keepMounted
            anchorEl={menuAnchor}
            open={Boolean(menuAnchor)}
            onClose={closeSizeExportMenu}
          >
            <div className={classes.menu}>
              <MenuItem
                data-testid={labelExportAs}
                className={classes.exportAs}
              >
                <Typography className={classes.menuHeader}>
                  {t(labelExportAs)}
                </Typography>
              </MenuItem>
              <Divider />

              <MenuItem
                data-testid={labelPNGAsDisplayed}
                onClick={(): void => convertToPng(1)}
              >
                <Typography variant="body2" className={classes.menuItem}>
                  {t(labelPNGAsDisplayed)}
                </Typography>
              </MenuItem>
              <MenuItem
                data-testid={labelPNGMediumSize}
                onClick={(): void => convertToPng(0.75)}
              >
                <Typography variant="body2" className={classes.menuItem}>
                  {t(labelPNGMediumSize)}
                </Typography>
              </MenuItem>
              <MenuItem
                data-testid={labelPNGSmallSize}
                onClick={(): void => convertToPng(0.5)}
              >
                <Typography variant="body2" className={classes.menuItem}>
                  {t(labelPNGSmallSize)}
                </Typography>
              </MenuItem>
              <Divider />
              <MenuItem data-testid={labelCSV} onClick={exportToCsv}>
                <Typography variant="body2" className={classes.menuItem}>
                  {t(labelCSV)}
                </Typography>
              </MenuItem>
            </div>
          </Menu>
        </>
      </ContentWithCircularLoading>
    </div>
  );
};

const MemoizedGraphActions = memoizeComponent<Props>({
  Component: GraphActions,
  memoProps: ['resource', 'timeline', 'performanceGraphRef', 'end', 'start']
});

export default MemoizedGraphActions;
