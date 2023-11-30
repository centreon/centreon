import { useState } from 'react';

import FileCopyIcon from '@mui/icons-material/FileCopy';

import { IconButton, TextField, useCopyToClipboard } from '@centreon/ui';

import { endAdornment } from './EndAdornment';

interface Props {
  token: string;
}

const TokenInput = ({ token }: Props): JSX.Element => {
  const [isVisible, setIsVisible] = useState(false);

  const successMessage = 'Token copied to the clipboard';
  const errorMessage = 'Token could not be copied';

  const { copy } = useCopyToClipboard({ errorMessage, successMessage });

  const handleVisibility = (): void => {
    setIsVisible(!isVisible);
  };

  return (
    <div
      style={{ alignItems: 'center', display: 'flex', flexDirection: 'row' }}
    >
      <TextField
        EndAdornment={endAdornment({ isVisible, onClick: handleVisibility })}
        dataTestId="tokenInput"
        id="token"
        label="Token"
        style={{ width: 380 }}
        type={isVisible ? 'text' : 'password'}
        value={token}
      />
      <IconButton
        ariaLabel="clipboard"
        onClick={() => {
          copy(token);
        }}
      >
        <FileCopyIcon fontSize="small" />
      </IconButton>
    </div>
  );
};

export default TokenInput;
