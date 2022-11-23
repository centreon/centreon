import useSnackbar from '../Snackbar/useSnackbar';

type CopyFunction = (text: string) => Promise<void>;

interface Result {
  copy: CopyFunction;
}

interface Props {
  errorMessage: string;
  successMessage: string;
}
const useCopyToClipboard = ({
  successMessage,
  errorMessage
}: Props): Result => {
  const { showSuccessMessage, showErrorMessage } = useSnackbar();

  const copy: CopyFunction = async (text) => {
    if (!navigator?.clipboard) {
      try {
        const textArea = document.createElement('input') as HTMLInputElement;
        textArea.setAttribute('type', 'text');
        textArea.setAttribute('id', 'copy');
        textArea.setAttribute('value', text);
        textArea.focus();
        textArea.select();

        const currentDiv = document.getElementById('root');
        document.body.insertBefore(textArea, currentDiv);

        textArea.focus();
        textArea.select();
        const success = document.execCommand('copy');
        document.body.removeChild(textArea);
        if (success) {
          showSuccessMessage(successMessage);

          return;
        }

        showErrorMessage(errorMessage);
      } catch (e) {
        showErrorMessage(errorMessage);
      }

      return;
    }

    await navigator.clipboard.writeText(text);
    showSuccessMessage(successMessage);
  };

  return { copy };
};

export default useCopyToClipboard;
