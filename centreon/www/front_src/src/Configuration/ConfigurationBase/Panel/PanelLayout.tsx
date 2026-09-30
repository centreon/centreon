import { Box, useTheme } from '@mui/material';

import { useAtom, useAtomValue } from 'jotai';
import { ReactElement } from 'react';

import { Form as FormType } from '../../models';
import { formStateAtom, panelWidthAtom } from '../atoms';
import Panel, { getDefaultPanelWidth, maxPanelWidth } from './Panel';
import useAvailableWidth from './useAvailableWidth';

// `PageLayout.Body` pads the page with `theme.spacing(0, 3, 1.5)`. The panel is
// flush to the page edges, so it bleeds back out by exactly that padding.
const pageBodyPadding = { bottom: 1.5, right: 3 };

interface Props {
  children: ReactElement;
  form: FormType;
  hasWriteAccess: boolean;
  width?: number;
}

const PanelLayout = ({
  children,
  form,
  hasWriteAccess,
  width
}: Props): ReactElement => {
  const theme = useTheme();

  const { id, isOpen } = useAtomValue(formStateAtom);

  const [draggedWidth, setDraggedWidth] = useAtom(panelWidthAtom);

  const { ref, availableWidth } = useAvailableWidth();

  const rightBleed = Number.parseFloat(theme.spacing(pageBodyPadding.right));

  const preferredWidth =
    draggedWidth ?? width ?? getDefaultPanelWidth(window.innerWidth);

  // Flush right, so anything wider than the page is clipped off it.
  const roomToGrow = availableWidth
    ? availableWidth + rightBleed
    : preferredWidth;

  const panelWidth = Math.min(preferredWidth, maxPanelWidth, roomToGrow);

  return (
    <div className="relative h-full" ref={ref}>
      {children}
      {isOpen && (
        <Box
          sx={{
            bottom: `-${theme.spacing(pageBodyPadding.bottom)}`,
            position: 'absolute',
            right: `-${theme.spacing(pageBodyPadding.right)}`,
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
    </div>
  );
};

export default PanelLayout;
