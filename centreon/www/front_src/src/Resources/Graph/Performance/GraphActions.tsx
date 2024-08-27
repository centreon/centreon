import { MouseEvent, MutableRefObject, useState } from 'react';

import { useAtomValue } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { makeStyles } from 'tss-react/mui';

import LaunchIcon from '@mui/icons-material/Launch';
import SaveAsImageIcon from '@mui/icons-material/SaveAlt';
import { Divider, Menu, MenuItem, useTheme } from '@mui/material';

import {
  ContentWithCircularLoading,
  IconButton,
  useLocaleDateTimeFormat
} from '@centreon/ui';

import FederatedComponent from '../../../components/FederatedComponents';
import { selectedResourceDetailsEndpointDerivedAtom } from '../../Details/detailsAtoms';
import { ResourceDetails } from '../../Details/models';
import { TimelineEvent } from '../../Details/tabs/Timeline/models';
import memoizeComponent from '../../memoizedComponent';
import { Resource, ResourceType } from '../../models';
import {
  labelAsDisplayed,
  labelCSV,
  labelExport,
  labelMediumSize,
  labelPerformancePage,
  labelSmallSize
} from '../../translatedLabels';

import exportToPng from './ExportableGraphWithTimeline/exportToPng';

interface Props {
  end: string;
  open: boolean;
  performanceGraphRef: MutableRefObject<HTMLDivElement | null>;
  resource?: Resource | ResourceDetails;
  start: string;
  timeline?: Array<TimelineEvent>;
}

const useStyles = makeStyles()((theme) => ({
  buttonGroup: {
    alignItems: 'center',
    columnGap: theme.spacing(1),
    display: 'flex',
    flexDirection: 'row'
  }
}));

const GraphActions = ({
  resource,
  timeline,
  performanceGraphRef,
  open,
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

  if (!open) {
    return null;
  }

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
            <MenuItem data-testid={labelExport} sx={{ cursor: 'auto' }}>
              {t(labelExport)}
            </MenuItem>
            <Divider />

            <MenuItem
              data-testid={labelAsDisplayed}
              onClick={(): void => convertToPng(1)}
            >
              {t(labelAsDisplayed)}
            </MenuItem>
            <MenuItem
              data-testid={labelMediumSize}
              onClick={(): void => convertToPng(0.75)}
            >
              {t(labelMediumSize)}
            </MenuItem>
            <MenuItem
              data-testid={labelSmallSize}
              onClick={(): void => convertToPng(0.5)}
            >
              {t(labelSmallSize)}
            </MenuItem>
            <Divider />
            <MenuItem data-testid={labelCSV} onClick={exportToCsv}>
              {t(labelCSV)}
            </MenuItem>
          </Menu>
        </>
      </ContentWithCircularLoading>
    </div>
  );
};

const MemoizedGraphActions = memoizeComponent<Props>({
  Component: GraphActions,
  memoProps: [
    'resourceParentName',
    'resourceName',
    'timeline',
    'performanceGraphRef',
    'renderAdditionalGraphActions',
    'end',
    'start'
  ]
});

export default MemoizedGraphActions;
