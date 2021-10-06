import * as React from 'react';

import { defaultAcknowledgement } from './UserContext';
import { Acknowledgement } from './types';

interface AcknowledgementState {
  acknowledgement: Acknowledgement;
  setAcknowledgement: React.Dispatch<React.SetStateAction<Acknowledgement>>;
}

const useAcknowledgement = (): AcknowledgementState => {
  const [acknowledgement, setAcknowledgement] = React.useState<Acknowledgement>(
    defaultAcknowledgement,
  );

  return { acknowledgement, setAcknowledgement };
};

export default useAcknowledgement;
