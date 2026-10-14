import { Box } from '@mui/material';

import { useAtom, useAtomValue } from 'jotai';
import { ReactElement } from 'react';

import { Form as FormType } from '../../models';
import { formStateAtom, panelWidthAtom } from '../atoms';
import Panel, { getDefaultPanelWidth, maxPanelWidth } from './Panel';
import useAvailableWidth from './useAvailableWidth';

interface Props {
  form: FormType;
  hasWriteAccess: boolean;
  width?: number;
}

const PanelLayout = ({ form, hasWriteAccess, width }: Props): ReactElement => {
  const { id, isOpen } = useAtomValue(formStateAtom);

  const [draggedWidth, setDraggedWidth] = useAtom(panelWidthAtom);

  const { ref, availableWidth } = useAvailableWidth();

  const preferredWidth =
    draggedWidth ?? width ?? getDefaultPanelWidth(window.innerWidth);

  // Flush right, so anything wider than the page is clipped off it.
  const panelWidth = Math.min(
    preferredWidth,
    maxPanelWidth,
    availableWidth || preferredWidth
  );

  return (
    // Spans the page, not the listing, so the panel reaches the top of it.
    // Transparent to the pointer: the listing underneath stays usable.
    <Box
      ref={ref}
      sx={{ inset: 0, pointerEvents: 'none', position: 'absolute' }}
    >
      {isOpen && (
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
          <Panel
            form={form}
            hasWriteAccess={hasWriteAccess}
            // Remounts on another resource, dropping the latched detail.
            key={id}
            onResize={setDraggedWidth}
            width={panelWidth}
          />
        </Box>
      )}
    </Box>
  );
};

export default PanelLayout;
