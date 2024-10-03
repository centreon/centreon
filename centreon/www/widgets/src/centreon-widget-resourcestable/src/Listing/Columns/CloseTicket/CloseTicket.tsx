import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { resourcesToCloseTicketAtom } from '../../../atom';
import { labelCloseTicket } from '../../translatedLabels';
import { useOpenTicketStyles } from '../Columns.styles';
import IconCloseTicket from '../Icons/CloseTicket';

const CloseTicket = ({ row }: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useOpenTicketStyles();
  const { t } = useTranslation();

  const setResourcesToCloseTicket = useSetAtom(resourcesToCloseTicketAtom);

  const askBeforeClosingTicket = (): void => {
    setResourcesToCloseTicket([
      {
        hostID: row.parent ? row?.parent?.id : row?.id,
        serviceID: row.parent ? row?.id : undefined
      }
    ]);
  };

  const ticket = row?.extra?.open_tickets?.tickets;
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
        size="large"
        onClick={askBeforeClosingTicket}
      >
        <IconCloseTicket />
      </IconButton>
    </div>
  );
};

export default CloseTicket;
