import SnackbarProvider from '../../Snackbar/SnackbarProvider';
import TestQueryProvider from '../TestQueryProvider';
import useMutationQuery, { Method } from '.';

interface Props {
  httpCodesBypassErrorSnackbar?: Array<number>;
}

const Sender = ({ httpCodesBypassErrorSnackbar }: Props): JSX.Element => {
  const mutation = useMutationQuery({
    getEndpoint: () => '/endpoint',
    httpCodesBypassErrorSnackbar,
    method: Method.POST
  });

  return (
    <button
      onClick={() =>
        mutation.mutateAsync({ payload: { a: 'a', b: 2, c: ['arr', 'ay'] } })
      }
      type="button"
    >
      Send
    </button>
  );
};

export const Default = (props: Props): JSX.Element => (
  <SnackbarProvider>
    <TestQueryProvider>
      <Sender {...props} />
    </TestQueryProvider>
  </SnackbarProvider>
);
