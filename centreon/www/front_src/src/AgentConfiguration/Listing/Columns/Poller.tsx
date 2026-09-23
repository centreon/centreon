import { Box } from '@mui/material';

import { usePluralizedTranslation } from '@centreon/ui';

import { isNil } from 'ramda';

import { labelPoller } from '../../translatedLabels';

const Poller = ({ row }: { row: Record<string, unknown> }) => {
  const typedRow = row as {
    pollers?: Array<unknown>;
    name?: string;
  };
  const { pluralizedT } = usePluralizedTranslation();

  const isSubNested = isNil(typedRow.pollers);

  return (
    <Box sx={{ pl: isSubNested ? 3 : 0 }}>
      {isSubNested
        ? typedRow.name
        : `${typedRow.pollers?.length} ${pluralizedT({ count: typedRow.pollers?.length ?? 0, label: labelPoller.toLocaleLowerCase() })}`}
    </Box>
  );
};

export default Poller;
