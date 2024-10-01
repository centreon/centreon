import { Box, TextField, Typography } from '@mui/material';

interface Options {
  text: string;
}
interface Props {
  panelOptions?: Options;
  setPanelOptions: (options: Options) => void;
}

const Input = ({ panelOptions, setPanelOptions }: Props): JSX.Element => {
  const changeInput = (event): void => {
    setPanelOptions({ text: event.target.value });
  };

  return (
    <Box>
      <TextField value={panelOptions?.text} onChange={changeInput} />
      <Typography>{panelOptions?.text}</Typography>
    </Box>
  );
};

export default Input;
