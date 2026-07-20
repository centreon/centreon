import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { resourcesToCloseTicketAtom } from '../../../atom';
import { labelCloseTicket } from '../../translatedLabels';
import { useOpenTicketStyles } from '../Columns.styles';
import IconCloseTicket from '../Icons/CloseTicket';

const CloseTicket = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useOpenTicketStyles();
  const { t } = useTranslation();

  const setResourcesToCloseTicket = useSetAtom(resourcesToCloseTicketAtom);

  const typedRow = row as {
    id?: number;
    extra?: { open_tickets?: { tickets?: { id?: number } } };
    parent?: {
      id?: number;
      extra?: { open_tickets?: { tickets?: { id?: number } } };
    };
  };

  const ticket =
    typedRow?.extra?.open_tickets?.tickets ||
    typedRow?.parent?.extra?.open_tickets?.tickets;

  const askBeforeClosingTicket = (): void => {
    setResourcesToCloseTicket([
      {
        hostID: (typedRow.parent
          ? typedRow?.parent?.id
          : typedRow?.id) as number,
        serviceID: typedRow.parent ? (typedRow?.id as number) : undefined,
        ticketId: ticket?.id
      }
    ]);
  };

  const hasTicket = !!ticket?.id;

  if (!hasTicket) {
    return null;
  }

  return (
    <div className={classes.actions}>
      <IconButton
        ariaLabel={t(labelCloseTicket)}
        color="primary"
        data-testid={labelCloseTicket}
        onClick={askBeforeClosingTicket}
        size="large"
      >
        <IconCloseTicket />
      </IconButton>
    </div>
  );
};

export default CloseTicket;
