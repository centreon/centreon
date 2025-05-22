import { SnackbarProvider, useSnackbar } from 'packages/ui/src';
import { useEffect } from 'react';



const WidgetSnack = ({ label}): JSX.Element => {
  const {
    showErrorMessage,
  } = useSnackbar();

 


  useEffect(() => {
    if(!label){
      return
    }
    
    showErrorMessage(label);
  }, [label]);

  return null;
};

export default WidgetSnack;

