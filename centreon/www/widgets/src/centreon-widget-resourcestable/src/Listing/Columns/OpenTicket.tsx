import { useTranslation } from 'react-i18next';
import { equals, or } from 'ramda';

import { useTheme } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import {
  labelOpenTicketForHost,
  labelOpenTicketForService
} from '../translatedLabels';

import IconCreateTicket from './Icons/CreateTicket';
import { useOpenTicketStyles } from './Columns.styles';

const OpenTicket = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useOpenTicketStyles();
  const { t } = useTranslation();

  const { palette } = useTheme();

  const { type } = row;

  const isHost = equals(type, 'host');
  const isService = equals(type, 'service');

  const createServiceTicket = (): void => {};

  const createHostTicket = (): void => {};

  return (
    <div className={classes.actions}>
      {isService && (
        <IconButton
          ariaLabel={t(labelOpenTicketForService)}
          color="primary"
          data-testid={labelOpenTicketForService}
          size="large"
          title={t(labelOpenTicketForService)}
          onClick={createServiceTicket}
        >
          <IconCreateTicket color={palette.success.main} type="S" />
        </IconButton>
      )}
      {or(isHost, isService) && (
        <IconButton
          ariaLabel={t(labelOpenTicketForHost)}
          color="primary"
          data-testid={labelOpenTicketForHost}
          size="large"
          title={t(labelOpenTicketForHost)}
          onClick={createHostTicket}
        >
          <IconCreateTicket color={palette.primary.main} type="H" />
        </IconButton>
      )}
    </div>
  );
};

export default OpenTicket;
