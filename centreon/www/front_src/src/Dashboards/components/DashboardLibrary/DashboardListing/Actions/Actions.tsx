import { useAtomValue, useSetAtom } from 'jotai';
import { isEmpty, map, pick } from 'ramda';

import { Box } from '@mui/material';
import { FileUploadOutlined as ExportIcon } from '@mui/icons-material';

import { IconButton } from '@centreon/ui/components';

import useIsViewerUser from '../useIsViewerUser';
import { selectedRowsAtom } from '../atom';

import Filter from './Filter';
import AddDashboard from './AddDashboard';
import { useActionsStyles } from './useActionsStyles';
import ViewMode from './ViewMode';

import { dashboardsToExportAtom } from 'www/front_src/src/Dashboards/atoms';

const Actions = ({ openConfig }: { openConfig: () => void }): JSX.Element => {
  const { classes } = useActionsStyles();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const setDashboardsToExport = useSetAtom(dashboardsToExportAtom);

  const isViewer = useIsViewerUser();

  const openExportPdfModal = (): void => {
    const dashboardsToExport = map(pick(['id', 'name']), selectedRows);

    setDashboardsToExport(dashboardsToExport);
  };

  return (
    <Box className={classes.actions}>
      {!isViewer && <AddDashboard openConfig={openConfig} />}
      <Filter />
      <IconButton
        aria-label="export"
        data-testid="export"
        disabled={isEmpty(selectedRows)}
        icon={<ExportIcon />}
        size="small"
        variant="primary"
        onClick={openExportPdfModal}
      />

      <ViewMode />
    </Box>
  );
};

export default Actions;
