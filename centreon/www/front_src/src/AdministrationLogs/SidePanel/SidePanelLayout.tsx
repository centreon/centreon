import { Box } from '@mui/material';

import { useState } from 'react';

import useAvailableWidth from '../../Configuration/ConfigurationBase/Panel/useAvailableWidth';
import type { SelectedTarget } from '../atoms';
import HistoryPanel from '../History/HistoryPanel';
import LogDetailPanel from '../LogDetail/LogDetailPanel';
import type { ActivityLog } from '../models';
import { defaultPanelWidth, maxPanelWidth } from './constants';

interface Props {
  log: ActivityLog | null;
  rows: Array<ActivityLog>;
  target: SelectedTarget | null;
}

// Same placement as the configuration panel (ConfigurationBase/PanelLayout):
// over the page, flush right, from the top of the page to its bottom. The layer
// lets the pointer through, so the listing underneath stays usable.
// It holds either the detail of a log or the history of an object; the width
// dragged by the user is kept when going from one to the other.
const SidePanelLayout = ({ log, rows, target }: Props): JSX.Element => {
  const [draggedWidth, setDraggedWidth] = useState<number | null>(null);
  const { ref, availableWidth } = useAvailableWidth();

  const preferredWidth = draggedWidth ?? defaultPanelWidth;
  const panelWidth = Math.min(
    preferredWidth,
    maxPanelWidth,
    availableWidth || preferredWidth
  );

  const getPanel = (): JSX.Element | null => {
    if (log) {
      return (
        <LogDetailPanel
          log={log}
          onResize={setDraggedWidth}
          rows={rows}
          width={panelWidth}
        />
      );
    }

    if (target) {
      return (
        <HistoryPanel
          key={`${target.type}:${target.id}`}
          onResize={setDraggedWidth}
          target={target}
          width={panelWidth}
        />
      );
    }

    return null;
  };

  const panel = getPanel();

  return (
    <Box
      ref={ref}
      sx={{ inset: 0, pointerEvents: 'none', position: 'absolute' }}
    >
      {panel && (
        <Box
          sx={{
            bottom: 0,
            pointerEvents: 'auto',
            position: 'absolute',
            right: 0,
            top: 0,
            zIndex: 10
          }}
        >
          {panel}
        </Box>
      )}
    </Box>
  );
};

export default SidePanelLayout;
