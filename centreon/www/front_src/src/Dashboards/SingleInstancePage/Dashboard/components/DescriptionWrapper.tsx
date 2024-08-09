import { Box } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

interface Props {
  children: JSX.Element;
}

const DescriptionWrapper = ({ children }: Props): JSX.Element => (
  <Box
    sx={{ maxHeight: 22, overflow: 'hidden', position: 'relative', zIndex: 1 }}
  >
    <Tooltip followCursor={false} label={children} placement="top">
      <div>{children}</div>
    </Tooltip>
  </Box>
);

export default DescriptionWrapper;
