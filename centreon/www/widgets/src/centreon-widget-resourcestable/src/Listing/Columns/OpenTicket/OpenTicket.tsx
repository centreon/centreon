import { useSetAtom } from 'jotai';
import { equals, or } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useTheme } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { resourcesToOpenTicketAtom } from '../../../atom';
import {
  labelOpenTicketForHost,
  labelOpenTicketForService
} from '../../translatedLabels';
import { useOpenTicketStyles } from '../Columns.styles';
import IconCreateTicket from '../Icons/CreateTicket';
import TooltipContent from '../Tooltip/Tooltip';

const OpenTicket = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useOpenTicketStyles();
  const { t } = useTranslation();
  const { palette } = useTheme();

  const setResourcesToOpenTicket = useSetAtom(resourcesToOpenTicketAtom);

  const { type } = row;
  const isHost = equals(type, 'host');
  const isService = equals(type, 'service');

  const createServiceTicket = (): void => {
    setResourcesToOpenTicket([{ hostID: row?.parent.id, serviceID: row?.id }]);
  };

  const createHostTicket = (): void => {
    setResourcesToOpenTicket([{ hostID: row?.id }]);
  };

  const ticket = row?.extra?.open_tickets?.tickets;
  const parentTicket = row?.parent?.extra?.open_tickets?.tickets;

  const hasTicket = !!ticket?.id;
  const didHostHasTicket = !!parentTicket?.id || (isHost && hasTicket);

  return (
    <div className={classes.actions}>
      {isService && (
        <IconButton
          ariaLabel={t(labelOpenTicketForService)}
          color="primary"
          data-testid={labelOpenTicketForService}
          disabled={hasTicket}
          size="large"
          title={TooltipContent({
            ...ticket,
            hasNoTicket: hasTicket,
            isHost: false
          })}
          tooltipClassName={hasTicket ? classes.tooltip : undefined}
          onClick={createServiceTicket}
        >
          <IconCreateTicket
            color={hasTicket ? palette.success.main : palette.primary.main}
            type="S"
          />
        </IconButton>
      )}
      {or(isHost, isService) && (
        <IconButton
          ariaLabel={t(labelOpenTicketForHost)}
          color="primary"
          data-testid={labelOpenTicketForHost}
          disabled={didHostHasTicket}
          size="large"
          title={TooltipContent({
            ...(isHost ? ticket : parentTicket),
            hasNoTicket: didHostHasTicket,
            isHost: true
          })}
          tooltipClassName={didHostHasTicket ? classes.tooltip : undefined}
          onClick={createHostTicket}
        >
          <IconCreateTicket
            color={
              didHostHasTicket ? palette.success.main : palette.primary.main
            }
            type="H"
          />
        </IconButton>
      )}
    </div>
  );
};

export default OpenTicket;
