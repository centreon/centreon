import { Box } from '@mui/material';

import { useState } from 'react';

import useAvailableWidth from '../../Configuration/ConfigurationBase/Panel/useAvailableWidth';
import type { SelectedTarget } from '../atoms';
import HistoryPanel, { maxPanelWidth } from './HistoryPanel';

const defaultPanelWidth = 560;

interface Props {
  target: SelectedTarget | null;
}

// Same placement as the configuration panel (ConfigurationBase/PanelLayout):
// over the page, flush right, from the top of the page to its bottom. The layer
// lets the pointer through, so the listing underneath stays usable.
const HistoryPanelLayout = ({ target }: Props): JSX.Element => {
  const [draggedWidth, setDraggedWidth] = useState<number | null>(null);
  const { ref, availableWidth } = useAvailableWidth();

  const preferredWidth = draggedWidth ?? defaultPanelWidth;
  const panelWidth = Math.min(
    preferredWidth,
    maxPanelWidth,
    availableWidth || preferredWidth
  );

  return (
    <Box
      ref={ref}
      sx={{ inset: 0, pointerEvents: 'none', position: 'absolute' }}
    >
      {target && (
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
          <HistoryPanel
            key={`${target.type}:${target.id}`}
            onResize={setDraggedWidth}
            target={target}
            width={panelWidth}
          />
        </Box>
      )}
    </Box>
  );
};

export default HistoryPanelLayout;
