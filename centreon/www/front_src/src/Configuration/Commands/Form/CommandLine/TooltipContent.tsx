import { ReactElement } from 'react';

import { Box, Divider, Typography } from '@mui/material';

import { LoadingSkeleton, truncate, useFetchQuery } from '@centreon/ui';
import { isNotNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { getPluginEndpoint } from '../../api';
import { labelCommandLine, labelOutput } from '../../translatedLabels';

interface Props {
  id: string;
  name: string;
}

const TooltipContent = ({ name, id }: Props): ReactElement => {
  const { t } = useTranslation();

  const { data, isFetching } = useFetchQuery({
    getEndpoint: () => getPluginEndpoint({ id }),
    getQueryKey: () => ['getPlugin', id],
    queryOptions: {
      enabled: isNotNil(id),
      suspense: false
    }
  });

  return (
    <Box>
      <Box className="flex flex-col items-center w-full bg-black text-white p-1">
        <Typography fontWeight="bold" className="text-white">
          {name}
        </Typography>
      </Box>
      <Box className="p-3">
        {isFetching ? (
          <LoadingSkeleton />
        ) : (
          <>
            <Typography fontWeight="bold">{t(labelCommandLine)}</Typography>
            <Typography variant="body2" className="text-text-secondary">
              {truncate({ content: data?.command_line, maxLength: 200 })}
            </Typography>

            <Divider className="mb-2 mt-2" />

            <Typography fontWeight="bold">{t(labelOutput)}</Typography>
            <Typography variant="body2" className="text-text-secondary">
              {truncate({ content: data?.output, maxLength: 200 })}
            </Typography>
          </>
        )}
      </Box>
    </Box>
  );
};

export default TooltipContent;
