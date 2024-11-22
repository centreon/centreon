import { T, always, cond, equals } from 'ramda';

import MailIcon from '@mui/icons-material/LocalPostOfficeOutlined';
import SmsIcon from '@mui/icons-material/TextsmsOutlined';
import { Box, Grid } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { ChannelsEnum, ResourcesType, ResourcesTypeEnum } from '../models';

interface FormatChannelProps {
  channel: ChannelsEnum;
}

const formatSingleResource = cond([
  [equals(ResourcesTypeEnum.HG), always('HG')],
  [equals(ResourcesTypeEnum.SG), always('SG')],
  [equals(ResourcesTypeEnum.BV), always('BV')],
  [T, always('N/A')]
]);

export const formatResourcesForListing = (
  resources: Array<ResourcesType>
): string => {
  const result = resources
    .map(({ type, count }) => {
      return `${count} ${formatSingleResource(type)}`;
    })
    .join(', ');

  return result;
};

export const FormatChannel = ({ channel }: FormatChannelProps): JSX.Element => {
  switch (channel) {
    case ChannelsEnum.Email:
      return <MailIcon fontSize="small" />;
    case ChannelsEnum.Sms:
      return <SmsIcon fontSize="small" />;
    default:
      return <Box />;
  }
};

export const FormatChannels = ({ row }: ComponentColumnProps): JSX.Element => {
  return (
    <Grid container spacing={1}>
      {row.channels.map((channel) => {
        return (
          <Grid item key={channel}>
            <FormatChannel channel={channel} />
          </Grid>
        );
      })}
    </Grid>
  );
};
