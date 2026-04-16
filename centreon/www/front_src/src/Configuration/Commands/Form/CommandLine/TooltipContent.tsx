import { Box, Divider, Typography } from '@mui/material';

import { LoadingSkeleton, truncate, useFetchQuery } from '@centreon/ui';

import { isNotEmpty, isNotNil } from 'ramda';
import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { getPluginEndpoint, pluginDetailsDecoder } from '../../api';
import { Plugin } from '../../models';
import { labelCommandLine, labelOutput } from '../../translatedLabels';

interface Props {
  name: string;
}

const TooltipContent = ({ name }: Props): ReactElement => {
  const { t } = useTranslation();

  const { data, isFetching } = useFetchQuery<Plugin>({
    decoder: pluginDetailsDecoder,
    getEndpoint: () => getPluginEndpoint({ name }),
    getQueryKey: () => ['getPlugin', name],
    queryOptions: {
      enabled: isNotNil(name) && isNotEmpty(name),
      suspense: false
    }
  });

  return (
    <Box>
      <Box className="flex flex-col items-center w-full bg-black text-white p-1">
        <Typography className="text-white" fontWeight="bold">
          {name}
        </Typography>
      </Box>
      <Box className="p-3">
        {isFetching ? (
          <LoadingSkeleton />
        ) : (
          <>
            <Typography fontWeight="bold">{t(labelCommandLine)}</Typography>
            <Typography className="text-text-secondary" variant="body2">
              {truncate({ content: data?.commandLine, maxLength: 200 })}
            </Typography>

            <Divider className="mb-2 mt-2" />

            <Typography fontWeight="bold">{t(labelOutput)}</Typography>
            <Typography className="text-text-secondary" variant="body2">
              {truncate({ content: data?.description || '', maxLength: 300 })}
            </Typography>
          </>
        )}
      </Box>
    </Box>
  );
};

export default TooltipContent;
