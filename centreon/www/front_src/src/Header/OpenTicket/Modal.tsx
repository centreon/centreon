import { useState } from 'react';

import { Button, Modal } from '@centreon/ui/components';

const OpenTicketModal = (): JSX.Element => {
  const [isOpen, setIsOpen] = useState(false);

  const open = (): void => setIsOpen(true);
  const close = (): void => setIsOpen(false);

  return (
    <>
      <Button onClick={open}>Open</Button>
      <Modal hasCloseButton open={isOpen} size="xlarge" onClose={close}>
        <Modal.Header>Create a ticket</Modal.Header>
        <Modal.Body>
          <iframe
            frameBorder={0}
            id="main-content"
            name="main-content"
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
