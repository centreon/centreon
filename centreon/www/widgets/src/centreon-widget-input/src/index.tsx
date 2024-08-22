import { createStore } from 'jotai';

import { Box, TextField, Typography } from '@mui/material';

import { Module } from '@centreon/ui';

interface Options {
  text: string;
}
interface Props {
  panelOptions?: Options;
  setPanelOptions: (options: Options) => void;
  store: ReturnType<typeof createStore>;
}

const Input = ({
  panelOptions,
  setPanelOptions,
  store
}: Props): JSX.Element => {
  const changeInput = (event): void => {
    setPanelOptions({ text: event.target.value });
  };

  return (
    <Module maxSnackbars={1} seedName="text2" store={store}>
      <Box>
        <TextField value={panelOptions?.text} onChange={changeInput} />
        <Typography>{panelOptions?.text}</Typography>
      </Box>
    </Module>
  );
};

export default Input;
