import { Box, useTheme } from '@mui/material';

import { useAtomValue } from 'jotai';
import { JSX, useState } from 'react';

import { Form as FormType } from '../../models';
import { formStateAtom } from '../atoms';
import Panel, { defaultPanelWidth, maxPanelWidth } from './Panel';
import useAvailableWidth from './useAvailableWidth';

// `PageLayout.Body` pads the page with `theme.spacing(0, 3, 1.5)`. The panel is
// flush to the page edges, so it bleeds back out by exactly that padding.
const pageBodyPadding = { bottom: 1.5, right: 3 };

interface Props {
  children: JSX.Element;
  form: FormType;
  hasWriteAccess: boolean;
  width?: number;
}

const PanelLayout = ({
  children,
  form,
  hasWriteAccess,
  width = defaultPanelWidth
}: Props): JSX.Element => {
  const theme = useTheme();

  const { id, isOpen } = useAtomValue(formStateAtom);

  const [requestedWidth, setRequestedWidth] = useState(width);

  const { ref, availableWidth } = useAvailableWidth();

  const rightBleed = Number.parseFloat(theme.spacing(pageBodyPadding.right));

  // Flush right, so anything wider than the page is clipped off it.
  const roomToGrow = availableWidth
    ? availableWidth + rightBleed
    : requestedWidth;

  const panelWidth = Math.min(requestedWidth, maxPanelWidth, roomToGrow);

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
            onResize={setRequestedWidth}
            width={panelWidth}
          />
        </Box>
      )}
    </div>
  );
};

export default PanelLayout;
