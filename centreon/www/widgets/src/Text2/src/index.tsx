import { Typography, TextField, Box } from '@mui/material';

const Text = ({ widgetOptions, setWidgetOptions }): JSX.Element => {
  const changeInput = (event) => {
    setWidgetOptions({ input: event.target.value });
  };

  return (
    <Box>
      <TextField value={widgetOptions?.input} onChange={changeInput} />
      <Typography>{widgetOptions?.input}</Typography>
    </Box>
  );
};

export default Text;
