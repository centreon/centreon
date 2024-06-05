import { useEffect, useState } from 'react';

import { equals } from 'ramda';

import { Button, Modal } from '@centreon/ui/components';
import { useSnackbar } from '@centreon/ui';

const OpenTicketModal = (): JSX.Element => {
  const [isOpen, setIsOpen] = useState(false);

  const { showSuccessMessage } = useSnackbar();

  const open = (): void => setIsOpen(true);
  const close = (): void => setIsOpen(false);

  const autoClose = (event: MessageEvent): void => {
    if (
      !equals(event.data.code, 0) ||
      !equals(event.source?.name, 'open-ticket')
    ) {
      return;
    }

    showSuccessMessage(
      `Ticket created. Ticket number: ${event.data.result.ticket_id}`
    );

    close();
  };

  useEffect(() => {
    if (!isOpen) {
      window.removeEventListener('message', autoClose);
    }

    window.addEventListener('message', autoClose);

    return () => {
      window.removeEventListener('message', autoClose);
    };
  }, [isOpen]);

  return (
    <>
      <Button onClick={open}>Open</Button>
      <Modal hasCloseButton open={isOpen} size="xlarge" onClose={close}>
        <Modal.Header>Create a ticket</Modal.Header>
        <Modal.Body>
          <iframe
            frameBorder={0}
            id="open-ticket"
            name="open-ticket"
            src="./main.get.php?p=60421&cmd=4&host_id=14&service_id=19"
            style={{ minHeight: '30vh', width: '100%' }}
            title="Main Content"
          />
        </Modal.Body>
      </Modal>
    </>
  );
};

export default OpenTicketModal;
