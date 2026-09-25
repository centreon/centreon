import { Box } from '@mui/material';

import { useAtomValue } from 'jotai';
import { JSX, useState } from 'react';

import { Form as FormType } from '../../models';
import { formStateAtom } from '../atoms';
import Panel, { defaultPanelWidth } from './Panel';
import useAvailableWidth from './useAvailableWidth';

// `PageLayout.Body` pads the page with `theme.spacing(0, 3, 1.5)`. The panel is
// flush to the page edges, so it bleeds back out by exactly that padding.
const pageBodyPadding = { bottom: 1.5, right: 3 };
const rightBleed = 24;

interface Props {
  children: JSX.Element;
  form: FormType;
  hasWriteAccess: boolean;
  width?: number;
}

// Lays the form panel over the listing, flush to the right: the listing keeps
// its width and its scroll position instead of reflowing when the panel opens.
const PanelLayout = ({
  children,
  form,
  hasWriteAccess,
  width = defaultPanelWidth
}: Props): JSX.Element => {
  const { isOpen } = useAtomValue(formStateAtom);

  // The module sets where the panel starts; the user may then drag it wider.
  const [requestedWidth, setRequestedWidth] = useState(width);

  const { ref, availableWidth } = useAvailableWidth();

  // Never wider than the page: the panel is flush right, so any excess would
  // be clipped, taking the form actions with it.
  const panelWidth = availableWidth
    ? Math.min(requestedWidth, availableWidth + rightBleed)
    : requestedWidth;

  return (
    <div className="relative h-full" ref={ref}>
      {children}
      {isOpen && (
        <Box
          sx={{
            bottom: (theme) => `-${theme.spacing(pageBodyPadding.bottom)}`,
            position: 'absolute',
            right: (theme) => `-${theme.spacing(pageBodyPadding.right)}`,
            top: 0,
            zIndex: 10
          }}
        >
          <Panel
            form={form}
            hasWriteAccess={hasWriteAccess}
            onResize={setRequestedWidth}
            width={panelWidth}
          />
        </Box>
      )}
    </div>
  );
};

export default PanelLayout;
